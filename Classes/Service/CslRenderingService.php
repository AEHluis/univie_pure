<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

use Seboettg\CiteProc\CiteProc;
use Seboettg\CiteProc\StyleSheet;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Log\LoggerInterface;
use Univie\UniviePure\Service\Cache\UnifiedCacheManager;

/**
 * CSL (Citation Style Language) rendering service
 *
 * Generates formatted citations using citeproc-php and CSL 1.0.2 specification.
 * Supports thousands of citation styles (APA, MLA, Chicago, IEEE, etc.)
 */
class CslRenderingService
{
    private const CACHE_LIFETIME = 14400; // 4 hours
    private const CSL_STYLES_PATH = 'EXT:univie_pure/Resources/Private/Csl/Styles/';
    private const DEFAULT_STYLE = 'apa';

    private array $loadedStyles = [];

    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly CslDataTransformer $dataTransformer,
        private readonly UnifiedCacheManager $cacheManager
    ) {}

    /**
     * Render research output as formatted citation
     *
     * @param array $data Research output data from API
     * @param string $style CSL style name (e.g., 'apa', 'mla', 'chicago', 'ieee')
     * @param string $format Output format ('html', 'text', 'rtf')
     * @return string Formatted citation
     */
    public function renderResearchOutput(array $data, string $style = self::DEFAULT_STYLE, string $format = 'html'): string
    {
        $uuid = $data['uuid'] ?? '';

        // Use unified cache key generation
        $cacheKey = $this->cacheManager->generateCacheKey(
            UnifiedCacheManager::LAYER_CSL,
            'research_output',
            $uuid,
            ['style' => $style, 'format' => $format]
        );

        // Get cache tags
        $cacheTags = $this->cacheManager->getCacheTags(
            UnifiedCacheManager::LAYER_CSL,
            'research_output',
            $uuid
        );

        if ($cached = $this->getCached($cacheKey)) {
            return $cached;
        }

        try {
            // Transform Pure data to CSL-JSON format
            $cslData = $this->dataTransformer->transformResearchOutput($data);

            // Get citation style
            $styleSheet = $this->getStyleSheet($style);

            // Generate citation using citeproc-php
            $citeProc = new CiteProc($styleSheet, 'en-US');
            $citation = $citeProc->render([$cslData], $format);

            // Cache the result with tags
            $this->setCached($cacheKey, $citation, $cacheTags);

            $this->logger->debug('CSL citation generated', [
                'uuid' => $uuid,
                'style' => $style,
                'format' => $format,
            ]);

            return $citation;

        } catch (\Exception $e) {
            $this->logger->error('CSL rendering failed', [
                'uuid' => $uuid,
                'style' => $style,
                'error' => $e->getMessage(),
            ]);

            // Return fallback citation
            return $this->renderFallback($data);
        }
    }

    /**
     * Render bibliography (multiple citations)
     *
     * @param array $items Array of research output data
     * @param string $style CSL style name
     * @param string $format Output format
     * @return string Formatted bibliography
     */
    public function renderBibliography(array $items, string $style = self::DEFAULT_STYLE, string $format = 'html'): string
    {
        try {
            // Transform all items to CSL-JSON
            $cslItems = array_map(
                fn($item) => $this->dataTransformer->transformResearchOutput($item),
                $items
            );

            // Get citation style
            $styleSheet = $this->getStyleSheet($style);

            // Generate bibliography
            $citeProc = new CiteProc($styleSheet, 'en-US');
            $bibliography = $citeProc->render($cslItems, $format);

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
     * Get available citation styles
     *
     * Automatically detects bundled CSL styles from Resources/Private/Csl/Styles/
     *
     * @return array Array of style information ['style-id' => 'Display Name']
     */
    public function getAvailableStyles(): array
    {
        // Check if styles are cached in memory
        static $availableStyles = null;

        if ($availableStyles !== null) {
            return $availableStyles;
        }

        // Get bundled CSL files
        $stylesPath = GeneralUtility::getFileAbsFileName(self::CSL_STYLES_PATH);
        $stylesDir = dirname($stylesPath);

        if (!is_dir($stylesDir)) {
            $this->logger->warning('CSL styles directory not found', ['path' => $stylesDir]);
            return $this->getFallbackStyles();
        }

        $cslFiles = glob($stylesDir . '/*.csl');

        if (empty($cslFiles)) {
            $this->logger->warning('No CSL styles found', ['path' => $stylesDir]);
            return $this->getFallbackStyles();
        }

        $styles = [];

        foreach ($cslFiles as $filePath) {
            $styleId = basename($filePath, '.csl');

            // Try to get display name from CSL file
            $displayName = $this->getStyleDisplayName($filePath);

            if ($displayName === null) {
                // Fallback: generate display name from filename
                $displayName = $this->generateDisplayName($styleId);
            }

            $styles[$styleId] = $displayName;
        }

        // Sort alphabetically by display name
        asort($styles);

        // Cache the result
        $availableStyles = $styles;

        return $styles;
    }

    /**
     * Get display name from CSL file
     *
     * @param string $filePath Path to CSL file
     * @return string|null Display name or null
     */
    private function getStyleDisplayName(string $filePath): ?string
    {
        try {
            $content = file_get_contents($filePath);

            if ($content === false) {
                return null;
            }

            // Parse XML
            $xml = @simplexml_load_string($content);

            if ($xml === false) {
                return null;
            }

            // Get title from <info><title>
            if (isset($xml->info->title)) {
                return trim((string)$xml->info->title);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate display name from style ID
     *
     * @param string $styleId Style ID (filename without .csl)
     * @return string Display name
     */
    private function generateDisplayName(string $styleId): string
    {
        // Convert dashes to spaces and capitalize words
        $name = str_replace('-', ' ', $styleId);
        $name = ucwords($name);

        // Handle common abbreviations
        $replacements = [
            'Apa' => 'APA',
            'Mla' => 'MLA',
            'Ieee' => 'IEEE',
            'Acm' => 'ACM',
            'Asa' => 'ASA',
            'Acs' => 'ACS',
            'Ama' => 'AMA',
            'Bmj' => 'BMJ',
            'Nlm' => 'NLM',
            'Cse' => 'CSE',
            'Iso' => 'ISO',
            'Din' => 'DIN',
            'Dgps' => 'DGPs',
            'Plos' => 'PLOS',
            'Jama' => 'JAMA',
            'Nejm' => 'NEJM',
            ' Of ' => ' of ',
            ' And ' => ' and ',
            ' For ' => ' for ',
            ' The ' => ' the ',
            ' In ' => ' in ',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $name);
    }

    /**
     * Get fallback styles (in case bundled styles not found)
     *
     * @return array Minimal set of common styles
     */
    private function getFallbackStyles(): array
    {
        return [
            'apa' => 'APA',
            'chicago-author-date' => 'Chicago (Author-Date)',
            'ieee' => 'IEEE',
            'vancouver' => 'Vancouver',
            'harvard-cite-them-right' => 'Harvard',
        ];
    }

    /**
     * Get CSL style sheet
     *
     * @param string $style Style name
     * @return string Style XML content
     * @throws \RuntimeException If style not found
     */
    private function getStyleSheet(string $style): string
    {
        // Check if already loaded
        if (isset($this->loadedStyles[$style])) {
            return $this->loadedStyles[$style];
        }

        // Try to load from extension resources
        $stylePath = GeneralUtility::getFileAbsFileName(self::CSL_STYLES_PATH . $style . '.csl');

        if (file_exists($stylePath)) {
            $content = file_get_contents($stylePath);
            $this->loadedStyles[$style] = $content;
            return $content;
        }

        // Try to load from official CSL repository (fallback for development)
        // ⚠️ This is NOT recommended for production - bundle styles instead
        $this->logger->warning('CSL style not found locally, downloading from GitHub', [
            'style' => $style,
            'path' => $stylePath,
            'production_warning' => 'For production, bundle styles using: ./scripts/bundle_csl_styles.sh',
        ]);

        $fallbackUrl = "https://raw.githubusercontent.com/citation-style-language/styles/master/{$style}.csl";

        try {
            $content = file_get_contents($fallbackUrl);

            if ($content !== false) {
                // Cache the downloaded style locally
                $this->cacheStyleLocally($style, $content);
                $this->loadedStyles[$style] = $content;

                $this->logger->info('CSL style downloaded and cached', [
                    'style' => $style,
                    'cached_to' => $stylePath,
                ]);

                return $content;
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to download CSL style from repository', [
                'style' => $style,
                'url' => $fallbackUrl,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback to default style
        if ($style !== self::DEFAULT_STYLE) {
            $this->logger->warning('Style not found, using default', [
                'requested' => $style,
                'using' => self::DEFAULT_STYLE,
            ]);
            return $this->getStyleSheet(self::DEFAULT_STYLE);
        }

        throw new \RuntimeException("CSL style '{$style}' not found and default style unavailable");
    }

    /**
     * Cache CSL style locally
     *
     * @param string $style Style name
     * @param string $content Style XML content
     */
    private function cacheStyleLocally(string $style, string $content): void
    {
        $stylePath = GeneralUtility::getFileAbsFileName(self::CSL_STYLES_PATH . $style . '.csl');
        $dir = dirname($stylePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($stylePath, $content);

        $this->logger->info('CSL style cached locally', ['style' => $style]);
    }

    /**
     * Generate cache key
     *
     * @param string $uuid Item UUID
     * @param string $style CSL style
     * @param string $format Output format
     * @return string Cache key
     */
    private function getCacheKey(string $uuid, string $style, string $format): string
    {
        return 'csl_' . md5($uuid . '_' . $style . '_' . $format);
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
     * Set cached citation with tags
     *
     * @param string $cacheKey Cache key
     * @param string $citation Citation to cache
     * @param array $tags Cache tags for selective clearing
     */
    private function setCached(string $cacheKey, string $citation, array $tags = []): void
    {
        $this->cache->set($cacheKey, $citation, $tags, self::CACHE_LIFETIME);
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
        if (isset($data['contributors'])) {
            foreach ($data['contributors'] as $contributor) {
                if (isset($contributor['name']['lastName'])) {
                    $authors[] = $contributor['name']['lastName'];
                }
            }
        }

        $authorsStr = implode(', ', array_slice($authors, 0, 3));
        if (count($authors) > 3) {
            $authorsStr .= ', et al.';
        }

        $year = $data['publicationYear'] ?? $data['year'] ?? '';
        $title = $data['title'] ?? 'Untitled';

        return sprintf('%s (%s). %s.', $authorsStr, $year, $title);
    }

    /**
     * Render fallback bibliography
     *
     * @param array $items Research outputs
     * @return string Simple bibliography
     */
    private function renderBibliographyFallback(array $items): string
    {
        $citations = array_map(fn($item) => $this->renderFallback($item), $items);
        return '<ol><li>' . implode('</li><li>', $citations) . '</li></ol>';
    }

    /**
     * Clear citation caches
     *
     * Uses unified cache manager
     */
    public function clearCache(): void
    {
        // Use unified cache manager to clear all CSL caches
        $this->cacheManager->clearCsl();

        $this->logger->info('CSL citation cache cleared via UnifiedCacheManager');
    }

    /**
     * Clear cache for specific item
     *
     * Uses unified cache manager - automatically clears via tags
     *
     * @param string $uuid Item UUID
     * @param string $type Resource type (defaults to research_output)
     */
    public function clearCacheForItem(string $uuid, string $type = 'research_output'): void
    {
        // Use unified cache manager - clears all styles/formats via tags
        $this->cacheManager->clearResource($type, $uuid);

        $this->logger->debug('CSL cache cleared for item via UnifiedCacheManager', [
            'uuid' => $uuid,
            'type' => $type,
        ]);
    }
}
