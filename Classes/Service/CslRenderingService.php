<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Log\LoggerInterface;
use Univie\UniviePure\Utility\LibraryLoader;

/**
 * CSL (Citation Style Language) rendering service
 *
 * Generates formatted citations using citeproc-php and CSL 1.0.2 specification.
 * Supports many citation styles (APA, MLA, Chicago, IEEE, etc.)
 *
 * CSL styles are downloaded on-demand from GitHub via CslStyleDownloadService.
 */
class CslRenderingService
{
    private const CACHE_LIFETIME = 14400; // 4 hours
    private const DEFAULT_STYLE = 'apa';
    private const DEFAULT_LOCALE = 'en-GB';

    /**
     * Mapping from Pure API locales to CSL/citeproc locales
     */
    private const LOCALE_MAPPING = [
        'de_DE' => 'de-DE',
        'en_GB' => 'en-GB',
        'de' => 'de-DE',
        'en' => 'en-GB',
    ];

    private array $loadedProcessors = [];

    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly CslDataTransformer $dataTransformer,
        private readonly CslStyleDownloadService $styleDownloader,
        private readonly CslLocaleManager $localeManager
    ) {}

    /**
     * Render research output as formatted citation
     *
     * @param array $data Research output data from API
     * @param string $style CSL style name (e.g., 'apa', 'mla', 'chicago', 'ieee')
     * @param string $mode Render mode ('bibliography' or 'citation')
     * @param string $locale Content locale for multilingual data (e.g., 'de_DE', 'en_GB')
     * @return string Formatted citation (HTML)
     */
    public function renderResearchOutput(
        array $data,
        string $style = self::DEFAULT_STYLE,
        string $mode = 'bibliography',
        string $locale = 'en_GB'
    ): string {
        $uuid = $data['uuid'] ?? '';
        $cacheKey = $this->getCacheKey($uuid, $style, $mode, $locale);

        if ($cached = $this->getCached($cacheKey)) {
            return $cached;
        }

        try {
            // Set locale for multilingual content extraction
            $this->dataTransformer->setPreferredLocale($locale);

            // Transform Pure data to CSL-JSON format
            $cslData = $this->dataTransformer->transformResearchOutput($data, $locale);

            // Get citeproc instance with appropriate locale
            $cslLocale = $this->mapToCslLocale($locale);
            $citeProc = $this->getCiteProc($style, $cslLocale);

            // Convert array to stdClass for citeproc-php
            $cslObject = json_decode(json_encode($cslData));

            // Generate citation - mode must be 'bibliography' or 'citation'
            $citation = $citeProc->render([$cslObject], $mode);

            // Cache the result
            $this->setCached($cacheKey, $citation);

            $this->logger->debug('CSL citation generated', [
                'uuid' => $uuid,
                'style' => $style,
                'mode' => $mode,
                'locale' => $locale,
            ]);

            return $citation;

        } catch (\Exception $e) {
            $this->logger->error('CSL rendering failed', [
                'uuid' => $uuid,
                'style' => $style,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            // Return fallback citation
            return $this->renderFallback($data);
        }
    }

    /**
     * Map Pure API locale to CSL locale format
     *
     * @param string $locale Pure locale (e.g., 'de_DE', 'en_GB')
     * @return string CSL locale (e.g., 'de-DE', 'en-GB')
     */
    private function mapToCslLocale(string $locale): string
    {
        return self::LOCALE_MAPPING[$locale] ?? self::DEFAULT_LOCALE;
    }

    /**
     * Render bibliography (multiple citations)
     *
     * Uses batch rendering which is 5-10x faster than individual rendering.
     *
     * @param array $items Array of research output data
     * @param string $style CSL style name
     * @param string $mode Render mode ('bibliography' or 'citation')
     * @param string $locale Content locale for multilingual data (e.g., 'de_DE', 'en_GB')
     * @return string Formatted bibliography (HTML)
     */
    public function renderBibliography(
        array $items,
        string $style = self::DEFAULT_STYLE,
        string $mode = 'bibliography',
        string $locale = 'en_GB'
    ): string {
        if (empty($items)) {
            return '';
        }

        try {
            // Set locale for multilingual content extraction
            $this->dataTransformer->setPreferredLocale($locale);

            // Transform all items to CSL-JSON
            $cslItems = $this->dataTransformer->transformBatch($items, $locale);

            // Get citeproc instance with appropriate locale
            $cslLocale = $this->mapToCslLocale($locale);
            $citeProc = $this->getCiteProc($style, $cslLocale);

            // Convert arrays to stdClass objects for citeproc-php
            $cslObjects = array_map(
                fn($item) => json_decode(json_encode($item)),
                $cslItems
            );

            // Generate bibliography - mode must be 'bibliography' or 'citation'
            $bibliography = $citeProc->render($cslObjects, $mode);

            return $bibliography;

        } catch (\Exception $e) {
            $this->logger->error('CSL bibliography rendering failed', [
                'style' => $style,
                'count' => count($items),
                'error' => $e->getMessage(),
            ]);

            // Return fallback
            return $this->renderBibliographyFallback($items);
        }
    }

    /**
     * Check if a style name is a CSL style (vs. a template view)
     *
     * @param string $style Style name
     * @return bool True if it's a CSL style
     */
    public function isCslStyle(string $style): bool
    {
        // Template views that are NOT CSL styles
        $templateViews = ['short', 'detailed', 'standard', 'portal-short', 'detailsPortal', 'bibtex', 'luhlong'];

        if (in_array($style, $templateViews, true)) {
            return false;
        }

        // Backend FlexForms and custom input may reference any valid CSL
        // repository id. Unknown valid ids should still reach the downloader.
        return preg_match('/^[a-z0-9][a-z0-9-]*(?:\.csl)?$/i', trim($style)) === 1;
    }

    /**
     * Get available citation styles
     *
     * @return array Array of style information ['style-id' => 'Display Name']
     */
    public function getAvailableStyles(): array
    {
        return $this->styleDownloader->getAvailableStyles();
    }

    /**
     * Get citeproc instance for style and locale
     *
     * @param string $style CSL style name
     * @param string $locale CSL locale (e.g., 'de-DE', 'en-GB')
     * @return \Seboettg\CiteProc\CiteProc CiteProc instance
     */
    private function getCiteProc(string $style, string $locale = self::DEFAULT_LOCALE): \Seboettg\CiteProc\CiteProc
    {
        $cacheKey = $style . '_' . $locale;

        // Check if already loaded
        if (isset($this->loadedProcessors[$cacheKey])) {
            return $this->loadedProcessors[$cacheKey];
        }

        // Ensure citeproc-php is loaded
        $this->ensureCiteprocLoaded();

        // Get style content via download service
        $styleContent = $this->styleDownloader->getStyle($style);

        // Create citeproc instance with specified locale
        $citeProc = new \Seboettg\CiteProc\CiteProc($styleContent, $locale);

        // Cache the processor
        $this->loadedProcessors[$cacheKey] = $citeProc;

        return $citeProc;
    }

    /**
     * Ensure citeproc-php library is loaded
     */
    private function ensureCiteprocLoaded(): void
    {
        if (class_exists(\Seboettg\CiteProc\CiteProc::class)) {
            // Already loaded, but ensure locales are available
            $this->localeManager->ensureLocalesAvailable();
            return;
        }

        // Use LibraryLoader to load the bundled library
        if (!LibraryLoader::loadCiteproc()) {
            throw new \RuntimeException(
                'citeproc-php library not available. Please ensure it is bundled in Libraries/citeproc-php/'
            );
        }

        // Ensure CSL locales are available for the library
        $this->localeManager->ensureLocalesAvailable();
    }

    /**
     * @deprecated Use LibraryLoader::loadCiteproc() instead
     */
    private function ensureCiteprocLoadedLegacy(): void
    {
        // Legacy method kept for reference
        $autoloadPath = GeneralUtility::getFileAbsFileName(
            'EXT:univie_pure/Libraries/citeproc-php/vendor/autoload.php'
        );

        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
            return;
        }

        throw new \RuntimeException(
            'citeproc-php library not found. Please install via composer or bundle in Libraries/citeproc-php/'
        );
    }

    /**
     * Generate cache key
     *
     * @param string $uuid Item UUID
     * @param string $style CSL style
     * @param string $mode Render mode (bibliography/citation)
     * @param string $locale Content locale
     * @return string Cache key
     */
    private function getCacheKey(string $uuid, string $style, string $mode, string $locale = 'en_GB'): string
    {
        return 'csl_' . md5($uuid . '_' . $style . '_' . $mode . '_' . $locale);
    }

    /**
     * Get cached citation
     *
     * @param string $cacheKey Cache key
     * @return string|null Cached citation or null
     */
    private function getCached(string $cacheKey): ?string
    {
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        return null;
    }

    /**
     * Set cached citation
     *
     * @param string $cacheKey Cache key
     * @param string $citation Citation to cache
     */
    private function setCached(string $cacheKey, string $citation): void
    {
        $this->cache->set($cacheKey, $citation, [], self::CACHE_LIFETIME);
    }

    /**
     * Render fallback citation (simple format)
     *
     * @param array $data Research output data
     * @return string Simple citation
     */
    private function renderFallback(array $data): string
    {
        $authors = [];

        // Try different author field formats
        if (isset($data['contributors'])) {
            foreach ($data['contributors'] as $contributor) {
                if (isset($contributor['name']['lastName'])) {
                    $authors[] = $contributor['name']['lastName'];
                }
            }
        } elseif (isset($data['personAssociations'])) {
            foreach ($data['personAssociations'] as $assoc) {
                if (isset($assoc['name']['lastName'])) {
                    $authors[] = $assoc['name']['lastName'];
                }
            }
        }

        $authorsStr = implode(', ', array_slice($authors, 0, 3));
        if (count($authors) > 3) {
            $authorsStr .= ', et al.';
        }

        $year = $data['publicationYear'] ?? $data['year'] ?? '';

        // Handle title - OpenAPI has title.value, XML has direct string
        $title = 'Untitled';
        if (isset($data['title'])) {
            if (is_array($data['title']) && isset($data['title']['value'])) {
                $title = $data['title']['value'];
            } elseif (is_string($data['title'])) {
                $title = $data['title'];
            }
        }

        return sprintf(
            '<div class="csl-entry csl-fallback">%s (%s). %s.</div>',
            htmlspecialchars($authorsStr, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)$year, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Render fallback bibliography
     *
     * @param array $items Research outputs
     * @return string Simple bibliography
     */
    private function renderBibliographyFallback(array $items): string
    {
        $html = '<div class="csl-bib-body csl-fallback">';
        foreach ($items as $item) {
            $html .= $this->renderFallback($item);
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Clear citation cache
     */
    public function clearCache(): void
    {
        $this->cache->flush();
        $this->loadedProcessors = [];
        $this->logger->info('CSL citation cache cleared');
    }
}
