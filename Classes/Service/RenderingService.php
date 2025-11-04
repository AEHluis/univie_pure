<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use Psr\Log\LoggerInterface;
use Univie\UniviePure\Service\Cache\UnifiedCacheManager;

/**
 * Rendering service for Pure API data
 *
 * Transforms structured JSON data from OpenAPI into HTML using Fluid templates.
 * Provides compatibility layer for templates expecting pre-rendered HTML.
 */
class RenderingService
{
    private const CACHE_LIFETIME = 14400; // 4 hours (same as API cache)

    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly UnifiedCacheManager $cacheManager,
        private readonly ?CslRenderingService $cslRenderingService = null
    ) {}

    /**
     * Render research output (publication) to HTML
     *
     * @param array $data Research output data from API
     * @param string $view View type (short, detailed, bibtex, csl, or CSL style name)
     * @param string|null $cslStyle CSL style name if view is 'csl' (e.g., 'apa', 'mla')
     * @return string Rendered HTML
     */
    public function renderResearchOutput(array $data, string $view = 'short', ?string $cslStyle = null): string
    {
        // Check if this is a CSL citation request
        if ($view === 'csl' || $this->isCslStyle($view)) {
            return $this->renderWithCsl($data, $cslStyle ?? $view);
        }

        return $this->render('ResearchOutput', $data, $view);
    }

    /**
     * Render research output using CSL (Citation Style Language)
     *
     * @param array $data Research output data
     * @param string $style CSL style name (e.g., 'apa', 'mla', 'chicago')
     * @return string Formatted citation HTML
     */
    private function renderWithCsl(array $data, string $style): string
    {
        if (!$this->cslRenderingService) {
            $this->logger->warning('CSL rendering requested but CslRenderingService not available');
            return $this->renderFallback($data, 'ResearchOutput');
        }

        try {
            return $this->cslRenderingService->renderResearchOutput($data, $style, 'html');
        } catch (\Exception $e) {
            $this->logger->error('CSL rendering failed, using fallback', [
                'uuid' => $data['uuid'] ?? 'unknown',
                'style' => $style,
                'error' => $e->getMessage(),
            ]);
            return $this->renderFallback($data, 'ResearchOutput');
        }
    }

    /**
     * Check if view name is a CSL style
     *
     * @param string $view View/style name
     * @return bool True if it's a CSL style
     */
    public function isCslStyle(string $view): bool
    {
        if (!$this->cslRenderingService) {
            return false;
        }

        $availableStyles = $this->cslRenderingService->getAvailableStyles();
        return isset($availableStyles[$view]);
    }

    /**
     * Render bibliography (multiple items) using CSL
     *
     * This is optimized for list views - renders all items in one batch
     * instead of creating a CiteProc instance for each item.
     *
     * @param array $items Array of research output data
     * @param string $style CSL style name (e.g., 'apa', 'mla')
     * @return string Full bibliography HTML with all items
     */
    public function renderBibliography(array $items, string $style): string
    {
        if (!$this->cslRenderingService) {
            $this->logger->warning('CSL rendering requested but CslRenderingService not available');
            return '';
        }

        try {
            return $this->cslRenderingService->renderBibliography($items, $style, 'html');
        } catch (\Exception $e) {
            $this->logger->error('CSL bibliography rendering failed', [
                'style' => $style,
                'itemCount' => count($items),
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Render person to HTML
     *
     * @param array $data Person data from API
     * @param string $view View type (short, detailed)
     * @return string Rendered HTML
     */
    public function renderPerson(array $data, string $view = 'short'): string
    {
        return $this->render('Person', $data, $view);
    }

    /**
     * Render project to HTML
     *
     * @param array $data Project data from API
     * @param string $view View type (short, detailed)
     * @return string Rendered HTML
     */
    public function renderProject(array $data, string $view = 'short'): string
    {
        return $this->render('Project', $data, $view);
    }

    /**
     * Render organisational unit to HTML
     *
     * @param array $data Organisation data from API
     * @param string $view View type (short, detailed)
     * @return string Rendered HTML
     */
    public function renderOrganisation(array $data, string $view = 'short'): string
    {
        return $this->render('Organisation', $data, $view);
    }

    /**
     * Render data set to HTML
     *
     * @param array $data Data set data from API
     * @param string $view View type (short, detailed)
     * @return string Rendered HTML
     */
    public function renderDataSet(array $data, string $view = 'short'): string
    {
        return $this->render('DataSet', $data, $view);
    }

    /**
     * Render equipment to HTML
     *
     * @param array $data Equipment data from API
     * @param string $view View type (short, detailed)
     * @return string Rendered HTML
     */
    public function renderEquipment(array $data, string $view = 'short'): string
    {
        return $this->render('Equipment', $data, $view);
    }

    /**
     * Generic render method
     *
     * @param string $type Content type (ResearchOutput, Person, etc.)
     * @param array $data Data to render
     * @param string $view View template name
     * @return string Rendered HTML
     */
    private function render(string $type, array $data, string $view): string
    {
        $uuid = $data['uuid'] ?? '';

        // Use unified cache key generation
        $cacheKey = $this->cacheManager->generateCacheKey(
            UnifiedCacheManager::LAYER_RENDER,
            $type,
            $uuid,
            ['view' => $view]
        );

        // Get cache tags
        $cacheTags = $this->cacheManager->getCacheTags(
            UnifiedCacheManager::LAYER_RENDER,
            $type,
            $uuid
        );

        if ($cached = $this->getCached($cacheKey)) {
            return $cached;
        }

        try {
            $templatePath = $this->getTemplatePath($type, $view);

            // Check if template exists, otherwise use fallback
            if (!file_exists($templatePath)) {
                $this->logger->warning('Rendering template not found, using fallback', [
                    'type' => $type,
                    'view' => $view,
                    'path' => $templatePath,
                ]);
                return $this->renderFallback($data, $type);
            }

            $fluidView = $this->createView();
            $fluidView->setTemplatePathAndFilename($templatePath);
            $fluidView->assignMultiple([
                'data' => $data,
                'view' => $view,
                'type' => $type,
            ]);

            $rendered = $fluidView->render();

            // Cache the result with tags
            $this->setCached($cacheKey, $rendered, $cacheTags);

            return $rendered;

        } catch (\Exception $e) {
            $this->logger->error('Rendering failed', [
                'type' => $type,
                'view' => $view,
                'error' => $e->getMessage(),
            ]);

            // Return fallback on error
            return $this->renderFallback($data, $type);
        }
    }

    /**
     * Create Fluid Standalone View
     *
     * @return StandaloneView
     */
    private function createView(): StandaloneView
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);

        // Set partial and layout paths
        $view->setPartialRootPaths([
            GeneralUtility::getFileAbsFileName('EXT:univie_pure/Resources/Private/Partials'),
        ]);
        $view->setLayoutRootPaths([
            GeneralUtility::getFileAbsFileName('EXT:univie_pure/Resources/Private/Layouts'),
        ]);

        return $view;
    }

    /**
     * Get template file path
     *
     * @param string $type Content type
     * @param string $view View name
     * @return string Absolute file path
     */
    private function getTemplatePath(string $type, string $view): string
    {
        return GeneralUtility::getFileAbsFileName(
            "EXT:univie_pure/Resources/Private/Templates/Renderings/{$type}/{$view}.html"
        );
    }

    /**
     * Generate cache key
     *
     * @param string $type Content type
     * @param string $uuid Item UUID
     * @param string $view View name
     * @return string Cache key
     */
    private function getCacheKey(string $type, string $uuid, string $view): string
    {
        return 'rendering_' . md5($type . '_' . $uuid . '_' . $view);
    }

    /**
     * Get cached rendering
     *
     * @param string $cacheKey Cache key
     * @return string|null Cached HTML or null
     */
    private function getCached(string $cacheKey): ?string
    {
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        return null;
    }

    /**
     * Set cached rendering with tags
     *
     * @param string $cacheKey Cache key
     * @param string $html HTML to cache
     * @param array $tags Cache tags for selective clearing
     */
    private function setCached(string $cacheKey, string $html, array $tags = []): void
    {
        $this->cache->set($cacheKey, $html, $tags, self::CACHE_LIFETIME);
    }

    /**
     * Render fallback HTML when template is missing or error occurs
     *
     * @param array $data Data to render
     * @param string $type Content type
     * @return string Basic HTML output
     */
    private function renderFallback(array $data, string $type): string
    {
        $uuid = $data['uuid'] ?? 'unknown';
        $title = $data['title'] ?? $data['name'] ?? 'Untitled';

        return sprintf(
            '<div class="rendering_%s fallback" data-uuid="%s">
                <h4>%s</h4>
                <p><em>Rendering template not available</em></p>
            </div>',
            strtolower($type),
            htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Clear all rendering caches
     *
     * Uses unified cache manager - automatically clears CSL caches via tags
     */
    public function clearCache(): void
    {
        // Use unified cache manager to clear all rendering caches
        // This automatically clears CSL caches via tags (no manual cascade needed)
        $this->cacheManager->clearRendering();

        $this->logger->info('Rendering cache cleared via UnifiedCacheManager');
    }

    /**
     * Clear cache for specific item
     *
     * Uses unified cache manager - automatically clears all layers via tags
     *
     * @param string $uuid Item UUID
     * @param string $type Resource type (optional, will try all types if not specified)
     */
    public function clearCacheForItem(string $uuid, string $type = ''): void
    {
        if (!empty($type)) {
            // Clear specific resource type
            $this->cacheManager->clearResource($type, $uuid);
        } else {
            // Try clearing all resource types (if type unknown)
            $types = ['person', 'research_output', 'project', 'organisation', 'dataset', 'equipment'];
            foreach ($types as $resourceType) {
                $this->cacheManager->clearResource($resourceType, $uuid);
            }
        }

        $this->logger->debug('Rendering cache cleared for item via UnifiedCacheManager', [
            'uuid' => $uuid,
            'type' => $type ?: 'all',
        ]);
    }
}
