<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Log\LoggerInterface;

/**
 * Manages CSL (Citation Style Language) locale files
 *
 * Downloads and caches locale files needed by citeproc-php.
 * Uses TYPO3's transient storage for downloaded JSON files.
 */
class CslLocaleManager
{
    private const LOCALES_REPO_BASE = 'https://raw.githubusercontent.com/citation-style-language/locales/master/';
    private const REQUIRED_LOCALES = ['de-DE', 'en-GB', 'en-US'];

    private ?ClientInterface $httpClient = null;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Get HTTP client with proxy configuration
     */
    private function getHttpClient(): ClientInterface
    {
        if ($this->httpClient === null) {
            $this->httpClient = HttpClientFactory::create();
        }
        return $this->httpClient;
    }

    /**
     * Ensure locales are available for citeproc-php
     *
     * Creates the expected directory structure in the bundled library
     * and downloads necessary locale files if missing.
     *
     * Note: Locale files are optional - citeproc-php will use built-in
     * defaults if locale XMLs are not available.
     */
    public function ensureLocalesAvailable(): void
    {
        $localesDir = $this->getLocalesDirectory();

        // Create directory if it doesn't exist
        if (!is_dir($localesDir)) {
            try {
                GeneralUtility::mkdir_deep($localesDir);
            } catch (\Exception $e) {
                $this->logger->warning('Could not create locales directory', [
                    'path' => $localesDir,
                    'error' => $e->getMessage()
                ]);
                return;
            }
        }

        // Try to ensure locales.json metadata file exists
        $this->ensureLocalesMetadata($localesDir);

        // Try to ensure required locale files exist (optional - won't fail if unavailable)
        foreach (self::REQUIRED_LOCALES as $locale) {
            $this->ensureLocaleFile($localesDir, $locale);
        }
    }

    /**
     * Get the locales directory path (in bundled citeproc-php library)
     */
    private function getLocalesDirectory(): string
    {
        return GeneralUtility::getFileAbsFileName(
            'EXT:univie_pure/Libraries/citeproc-php/vendor/citation-style-language/locales'
        );
    }

    /**
     * Ensure locales.json metadata file exists
     */
    private function ensureLocalesMetadata(string $localesDir): void
    {
        $metadataFile = $localesDir . '/locales.json';

        if (file_exists($metadataFile)) {
            return;
        }

        $this->logger->info('Downloading CSL locales metadata');

        // Download locales.json
        $url = self::LOCALES_REPO_BASE . 'locales.json';
        $content = $this->downloadFile($url);

        if ($content) {
            GeneralUtility::writeFile($metadataFile, $content);
            $this->logger->info('CSL locales metadata downloaded successfully');
        } else {
            $this->logger->warning('Failed to download CSL locales metadata', ['url' => $url]);
        }
    }

    /**
     * Ensure a specific locale file exists
     */
    private function ensureLocaleFile(string $localesDir, string $locale): void
    {
        $localeFile = $localesDir . '/locales-' . $locale . '.xml';

        if (file_exists($localeFile)) {
            return;
        }

        $this->logger->info('Downloading CSL locale', ['locale' => $locale]);

        // Download locale XML file
        $url = self::LOCALES_REPO_BASE . 'locales-' . $locale . '.xml';
        $content = $this->downloadFile($url);

        if ($content) {
            GeneralUtility::writeFile($localeFile, $content);
            $this->logger->info('CSL locale downloaded successfully', ['locale' => $locale]);
        } else {
            $this->logger->warning('Failed to download CSL locale', [
                'locale' => $locale,
                'url' => $url
            ]);
        }
    }

    /**
     * Download a file with proxy support
     *
     * Uses HttpClientFactory to create a Guzzle client that respects PURE_PROXY from .env
     */
    private function downloadFile(string $url): ?string
    {
        try {
            $client = $this->getHttpClient();
            $request = new Request('GET', $url, [
                'User-Agent' => 'TYPO3-UniviePure/1.0',
            ]);

            $response = $client->sendRequest($request);

            if ($response->getStatusCode() === 200) {
                return $response->getBody()->getContents();
            }

            $this->logger->warning('Download failed', [
                'url' => $url,
                'status_code' => $response->getStatusCode(),
            ]);

        } catch (\Exception $e) {
            $this->logger->warning('Download error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Clear downloaded locales (force re-download)
     */
    public function clearLocales(): void
    {
        $localesDir = $this->getLocalesDirectory();

        if (is_dir($localesDir)) {
            GeneralUtility::rmdir($localesDir, true);
            $this->logger->info('CSL locales cleared');
        }
    }
}
