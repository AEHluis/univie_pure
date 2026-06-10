<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service for downloading CSL (Citation Style Language) files from GitHub
 *
 * Downloads CSL styles on-demand from the official citation-style-language repository.
 * Stores downloaded styles locally in typo3temp/var/tx_univiepure/styles/ for caching.
 */
class CslStyleDownloadService
{
    private const CSL_GITHUB_BASE_URL = 'https://raw.githubusercontent.com/citation-style-language/styles/master/';
    private const LOCAL_STYLES_PATH = 'tx_univiepure/styles/';
    private const CACHE_LIFETIME_DAYS = 30;

    /**
     * Popular CSL styles that are commonly used
     * Maps style identifier to filename in CSL repository
     *
     * Note: Some styles like 'vancouver' map to 'elsevier-vancouver.csl' because
     * the standalone 'vancouver.csl' doesn't exist in the CSL repository.
     */
    private const COMMON_STYLES = [
        'apa' => 'apa.csl',
        'apa-6th-edition' => 'apa-6th-edition.csl',
        'mla' => 'modern-language-association.csl',
        'modern-language-association' => 'modern-language-association.csl',
        'chicago-author-date' => 'chicago-author-date.csl',
        'chicago-author-date-17th-edition' => 'chicago-author-date-17th-edition.csl',
        'chicago-notes-bibliography' => 'chicago-notes-bibliography.csl',
        'chicago-notes-bibliography-17th-edition' => 'chicago-notes-bibliography-17th-edition.csl',
        'ieee' => 'ieee.csl',
        'harvard' => 'harvard-cite-them-right.csl',
        'harvard-cite-them-right' => 'harvard-cite-them-right.csl',
        'vancouver' => 'elsevier-vancouver.csl',
        'elsevier-vancouver' => 'elsevier-vancouver.csl',
        'nature' => 'nature.csl',
        'science' => 'science.csl',
        'cell' => 'cell.csl',
        'elsevier-harvard' => 'elsevier-harvard.csl',
        'springer-basic-author-date' => 'springer-basic-author-date.csl',
        'din-1505-2' => 'din-1505-2.csl',
        'din-1505-2-alphanumeric' => 'din-1505-2-alphanumeric.csl',
        'din-1505-2-numeric' => 'din-1505-2-numeric.csl',
        'din-1505-2-numeric-alphabetical' => 'din-1505-2-numeric-alphabetical.csl',
        'iso690-author-date-de' => 'iso690-author-date-de.csl',
        'nlm-citation-sequence' => 'nlm-citation-sequence.csl',
        'zeithistorische-forschungen' => 'zeithistorische-forschungen.csl',
    ];

    private string $localStylesPath;
    private ?ClientInterface $proxyClient = null;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly LoggerInterface $logger
    ) {
        $this->localStylesPath = Environment::getVarPath() . '/' . self::LOCAL_STYLES_PATH;
        $this->ensureStylesDirectoryExists();
    }

    /**
     * Get HTTP client with proxy configuration
     *
     * Uses HttpClientFactory to create a client that respects PURE_PROXY from .env
     */
    private function getHttpClient(): ClientInterface
    {
        if ($this->proxyClient === null) {
            $this->proxyClient = HttpClientFactory::create();
        }
        return $this->proxyClient;
    }

    /**
     * Get CSL style content by name
     *
     * Downloads the style from GitHub if not cached locally.
     *
     * @param string $styleName Style name (e.g., 'apa', 'mla', 'ieee')
     * @return string CSL XML content
     */
    public function getStyle(string $styleName): string
    {
        $styleName = $this->normalizeStyleName($styleName);

        $this->logger->debug('CSL getStyle called', ['style' => $styleName]);

        // Try local cached version first
        $localPath = $this->getLocalStylePath($styleName);
        if ($this->isLocalStyleValid($localPath)) {
            $this->logger->debug('CSL using cached style', ['path' => $localPath]);
            return file_get_contents($localPath);
        }

        // Try to download from GitHub
        $content = $this->downloadStyle($styleName);
        if ($content !== null) {
            $saved = $this->saveLocalStyle($localPath, $content);
            $this->logger->debug('CSL style save result', ['path' => $localPath, 'saved' => $saved]);
            return $content;
        }

        throw new \RuntimeException(sprintf('CSL style "%s" could not be downloaded', $styleName));
    }

    /**
     * Get path to CSL style file
     *
     * @param string $styleName Style name
     * @return string|null Path to style file or null if not available
     */
    public function getStylePath(string $styleName): ?string
    {
        $styleName = $this->normalizeStyleName($styleName);

        // Try local cached version first
        $localPath = $this->getLocalStylePath($styleName);
        if ($this->isLocalStyleValid($localPath)) {
            return $localPath;
        }

        // Try to download and cache
        $content = $this->downloadStyle($styleName);
        if ($content !== null) {
            $this->saveLocalStyle($localPath, $content);
            return $localPath;
        }

        return null;
    }

    /**
     * Get list of available styles
     *
     * Returns common styles that can be downloaded.
     *
     * @return array<string, string> Style name => Display name
     */
    public function getAvailableStyles(): array
    {
        $styles = [];

        foreach (self::COMMON_STYLES as $name => $filename) {
            $displayName = $this->formatDisplayName($name);
            $styles[$name] = $displayName;
        }

        return $styles;
    }

    /**
     * Check if a style is available (either cached or known downloadable)
     *
     * @param string $styleName Style name
     * @return bool
     */
    public function isStyleAvailable(string $styleName): bool
    {
        $styleName = $this->normalizeStyleName($styleName);

        // Check local cache
        $localPath = $this->getLocalStylePath($styleName);
        if ($this->isLocalStyleValid($localPath)) {
            return true;
        }

        // Check if it's a known common style
        if (isset(self::COMMON_STYLES[$styleName])) {
            return true;
        }

        return false;
    }

    /**
     * Clear all cached styles
     *
     * @return int Number of files deleted
     */
    public function clearCache(): int
    {
        $count = 0;

        if (is_dir($this->localStylesPath)) {
            $files = glob($this->localStylesPath . '*.csl');
            foreach ($files as $file) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }

        $this->logger->info('CSL style cache cleared', ['files_deleted' => $count]);
        return $count;
    }

    /**
     * Pre-download common styles for offline availability
     *
     * @return array<string, bool> Style name => success status
     */
    public function preloadCommonStyles(): array
    {
        $results = [];

        foreach (array_keys(self::COMMON_STYLES) as $styleName) {
            $content = $this->downloadStyle($styleName);
            $results[$styleName] = $content !== null;

            if ($content !== null) {
                $localPath = $this->getLocalStylePath($styleName);
                $this->saveLocalStyle($localPath, $content);
            }
        }

        $this->logger->info('Preloaded common CSL styles', [
            'total' => count($results),
            'successful' => count(array_filter($results)),
        ]);

        return $results;
    }

    /**
     * Normalize style name
     */
    private function normalizeStyleName(string $styleName): string
    {
        // Remove .csl extension if present
        $styleName = preg_replace('/\.csl$/i', '', $styleName);

        // Convert to lowercase
        $styleName = strtolower(trim((string)$styleName));

        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $styleName)) {
            throw new \InvalidArgumentException(sprintf('Invalid CSL style name "%s"', $styleName));
        }

        return $styleName;
    }

    /**
     * Get local style file path
     */
    private function getLocalStylePath(string $styleName): string
    {
        return $this->localStylesPath . $styleName . '.csl';
    }

    /**
     * Check if local style file exists and is valid (not expired)
     */
    private function isLocalStyleValid(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        // Check if file is not too old
        $fileAge = time() - filemtime($path);
        $maxAge = self::CACHE_LIFETIME_DAYS * 24 * 60 * 60;

        return $fileAge < $maxAge;
    }

    /**
     * Download style from GitHub
     *
     * Uses proxy-aware HTTP client from HttpClientFactory
     */
    private function downloadStyle(string $styleName): ?string
    {
        // Determine the filename on GitHub
        $filename = self::COMMON_STYLES[$styleName] ?? $styleName . '.csl';
        $url = self::CSL_GITHUB_BASE_URL . $filename;

        $this->logger->info('CSL download attempt', [
            'style' => $styleName,
            'filename' => $filename,
            'url' => $url,
        ]);

        try {
            // Use proxy-aware client from HttpClientFactory
            $client = $this->getHttpClient();
            $request = new Request('GET', $url, [
                'Accept' => 'application/xml, text/xml',
                'User-Agent' => 'TYPO3-UniviePure/1.0',
            ]);

            $response = $client->sendRequest($request);

            if ($response->getStatusCode() === 200) {
                $content = $response->getBody()->getContents();

                // Validate it's actually CSL XML
                if (str_contains($content, '<style') && str_contains($content, 'csl')) {
                    $this->logger->info('CSL download SUCCESS', [
                        'style' => $styleName,
                        'content_length' => strlen($content),
                    ]);
                    return $content;
                }

                $this->logger->warning('CSL download returned invalid content', [
                    'style' => $styleName,
                    'content_preview' => substr($content, 0, 100),
                ]);
            } else {
                $this->logger->warning('CSL download FAILED', [
                    'style' => $styleName,
                    'url' => $url,
                    'status_code' => $response->getStatusCode(),
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error downloading CSL style', [
                'style' => $styleName,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Save style content to local cache
     */
    private function saveLocalStyle(string $path, string $content): bool
    {
        $this->ensureStylesDirectoryExists();

        if (file_put_contents($path, $content) !== false) {
            $this->logger->debug('Saved CSL style to cache', ['path' => $path]);
            return true;
        }

        $this->logger->warning('Failed to save CSL style to cache', ['path' => $path]);
        return false;
    }

    /**
     * Ensure local styles directory exists
     */
    private function ensureStylesDirectoryExists(): void
    {
        if (!is_dir($this->localStylesPath)) {
            GeneralUtility::mkdir_deep($this->localStylesPath);
        }
    }

    /**
     * Format style name for display
     */
    private function formatDisplayName(string $styleName): string
    {
        // Convert dashes to spaces and capitalize
        $name = str_replace('-', ' ', $styleName);
        $name = ucwords($name);

        // Handle common abbreviations
        $replacements = [
            'Apa' => 'APA',
            'Mla' => 'MLA',
            'Ieee' => 'IEEE',
            'Din' => 'DIN',
            'Iso' => 'ISO',
        ];

        return strtr($name, $replacements);
    }
}
