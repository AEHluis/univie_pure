<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use Psr\Log\LoggerInterface;

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
        private readonly CslRenderingService $cslRenderingService
    ) {}

    /**
     * Render research output (publication) to HTML
     *
     * @param array $data Research output data from API
     * @param string $view View type (short, detailed, bibtex, csl, or CSL style name)
     * @param string|null $cslStyle CSL style name if view is 'csl' (e.g., 'apa', 'mla')
     * @param string $locale Content locale for multilingual data (e.g., 'de_DE', 'en_GB')
     * @return string Rendered HTML
     */
    public function renderResearchOutput(
        array $data,
        string $view = 'short',
        ?string $cslStyle = null,
        string $locale = 'en_GB'
    ): string {
        // Check if this is a CSL citation request
        if ($view === 'csl' || $this->isCslStyle($view)) {
            return $this->renderWithCsl($data, $cslStyle ?? $view, $locale);
        }

        return $this->render('ResearchOutput', $data, $view, $locale);
    }

    /**
     * Render research output using CSL (Citation Style Language)
     *
     * @param array $data Research output data
     * @param string $style CSL style name (e.g., 'apa', 'mla', 'chicago')
     * @param string $locale Content locale for multilingual data
     * @return string Formatted citation HTML
     */
    private function renderWithCsl(array $data, string $style, string $locale = 'en_GB'): string
    {
        try {
            return $this->cslRenderingService->renderResearchOutput($data, $style, 'bibliography', $locale);
        } catch (\Exception $e) {
            $this->logger->error('CSL rendering failed, using fallback', [
                'uuid' => $data['uuid'] ?? 'unknown',
                'style' => $style,
                'locale' => $locale,
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
        return $this->cslRenderingService->isCslStyle($view);
    }

    /**
     * Render bibliography (multiple items) using CSL
     *
     * This is optimized for list views - renders all items in one batch
     * instead of creating a CiteProc instance for each item.
     *
     * @param array $items Array of research output data
     * @param string $style CSL style name (e.g., 'apa', 'mla')
     * @param string $locale Content locale for multilingual data (e.g., 'de_DE', 'en_GB')
     * @return string Full bibliography HTML with all items
     */
    public function renderBibliography(array $items, string $style, string $locale = 'en_GB'): string
    {
        try {
            return $this->cslRenderingService->renderBibliography($items, $style, 'bibliography', $locale);
        } catch (\Exception $e) {
            $this->logger->error('CSL bibliography rendering failed', [
                'style' => $style,
                'locale' => $locale,
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
     * @param string $locale Content locale for cache differentiation
     * @return string Rendered HTML
     */
    private function render(string $type, array $data, string $view, string $locale = 'en_GB'): string
    {
        $uuid = $data['uuid'] ?? '';
        $cacheKey = $this->getCacheKey($type, $uuid, $view, $locale);

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
                'locale' => $locale,
            ]);

            $rendered = $fluidView->render();

            // Cache the result
            $this->setCached($cacheKey, $rendered);

            return $rendered;

        } catch (\Exception $e) {
            $this->logger->error('Rendering failed', [
                'type' => $type,
                'view' => $view,
                'locale' => $locale,
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
     * @param string $locale Content locale
     * @return string Cache key
     */
    private function getCacheKey(string $type, string $uuid, string $view, string $locale = 'en_GB'): string
    {
        return 'rendering_' . md5($type . '_' . $uuid . '_' . $view . '_' . $locale);
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
     * Set cached rendering
     *
     * @param string $cacheKey Cache key
     * @param string $html HTML to cache
     */
    private function setCached(string $cacheKey, string $html): void
    {
        $this->cache->set($cacheKey, $html, [], self::CACHE_LIFETIME);
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
            </div>',
            strtolower($type),
            htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Clear all rendering caches
     */
    public function clearCache(): void
    {
        $this->cache->flush();
        $this->cslRenderingService->clearCache();
        $this->logger->info('Rendering cache cleared');
    }
}
