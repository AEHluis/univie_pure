<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\OpenApi;

use Univie\UniviePure\Service\ApiServiceInterface;
use Univie\UniviePure\Service\RenderingService;

/**
 * OpenAPI service implementation
 *
 * Implements ApiServiceInterface using the new Pure OpenAPI (REST) endpoints.
 * Provides modern REST-based access to Pure research data.
 *
 * IMPORTANT: This service adds HTML rendering to structured JSON data from OpenAPI
 * to maintain compatibility with existing templates that expect pre-rendered HTML.
 */
class OpenApiService implements ApiServiceInterface
{
    public function __construct(
        private readonly OpenApiClient $client,
        private readonly OpenApiResponseParser $parser,
        private readonly MigrationHelper $migrationHelper,
        private readonly RenderingService $renderingService
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getPersons(array $params = []): array
    {
        $queryParams = $this->migrationHelper->convertToOpenApiParams($params);
        $response = $this->client->get('/persons', $queryParams);
        $collection = $this->parser->parseCollection($response);

        // Add HTML rendering to each item
        $view = $params['view'] ?? $params['rendering'] ?? 'short';
        $view = $this->migrationHelper->mapRendering($view);

        foreach ($collection['items'] as &$item) {
            $item['rendering'] = $this->renderingService->renderPerson($item, $view);
        }
        unset($item);

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function getPerson(string $uuid, array $params = []): ?array
    {
        $queryParams = $this->buildSingleItemParams($params);

        try {
            $response = $this->client->get("/persons/{$uuid}", $queryParams);

            // Add HTML rendering
            $view = $params['view'] ?? $params['rendering'] ?? 'detailed';
            $view = $this->migrationHelper->mapRendering($view);
            $response['rendering'] = $this->renderingService->renderPerson($response, $view);

            return $response;
        } catch (OpenApiException $e) {
            if ($e->isNotFoundError()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResearchOutputs(array $params = []): array
    {
        $queryParams = $this->migrationHelper->convertToOpenApiParams($params);
        $response = $this->client->get('/research-outputs', $queryParams);
        $collection = $this->parser->parseCollection($response);

        // Add HTML rendering to each item for template compatibility
        $view = $params['view'] ?? $params['rendering'] ?? 'short';
        $view = $this->migrationHelper->mapRendering($view);

        // Optimize CSL rendering: use batch rendering for citation styles
        if ($this->renderingService->isCslStyle($view)) {
            $this->addBatchCslRendering($collection['items'], $view);
        } else {
            $this->addIndividualRendering($collection['items'], $view);
        }

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function getResearchOutput(string $uuid, array $params = []): ?array
    {
        $queryParams = $this->buildSingleItemParams($params);

        try {
            $response = $this->client->get("/research-outputs/{$uuid}", $queryParams);

            // Add HTML rendering for template compatibility
            $view = $params['view'] ?? $params['rendering'] ?? 'detailed';
            $view = $this->migrationHelper->mapRendering($view);
            $response['rendering'] = $this->renderingService->renderResearchOutput($response, $view);

            return $response;
        } catch (OpenApiException $e) {
            if ($e->isNotFoundError()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResearchOutputBibtex(string $uuid, array $params = []): ?string
    {
        try {
            $response = $this->client->get("/research-outputs/{$uuid}", [
                'format' => 'bibtex',
            ], [
                'Accept' => 'text/plain',
            ]);

            // BibTeX comes as plain text
            return $response['content'] ?? $response['bibtex'] ?? null;
        } catch (OpenApiException $e) {
            if ($e->isNotFoundError()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getProjects(array $params = []): array
    {
        $queryParams = $this->migrationHelper->convertToOpenApiParams($params);
        $response = $this->client->get('/projects', $queryParams);
        $collection = $this->parser->parseCollection($response);

        // Add HTML rendering to each item
        $view = $params['view'] ?? $params['rendering'] ?? 'short';
        $view = $this->migrationHelper->mapRendering($view);

        foreach ($collection['items'] as &$item) {
            $item['rendering'] = $this->renderingService->renderProject($item, $view);
        }
        unset($item);

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function getProject(string $uuid, array $params = []): ?array
    {
        $queryParams = $this->buildSingleItemParams($params);

        try {
            $response = $this->client->get("/projects/{$uuid}", $queryParams);
            return $response;
        } catch (OpenApiException $e) {
            if ($e->isNotFoundError()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOrganisationalUnits(array $params = []): array
    {
        $queryParams = $this->migrationHelper->convertToOpenApiParams($params);
        // Note: Endpoint name changed from 'organisational-units' to 'organizations' in OpenAPI
        $response = $this->client->get('/organizations', $queryParams);

        return $this->parser->parseCollection($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrganisationalUnit(string $uuid, array $params = []): ?array
    {
        $queryParams = $this->buildSingleItemParams($params);

        try {
            $response = $this->client->get("/organizations/{$uuid}", $queryParams);
            return $response;
        } catch (OpenApiException $e) {
            if ($e->isNotFoundError()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSets(array $params = []): array
    {
        $queryParams = $this->migrationHelper->convertToOpenApiParams($params);
        $response = $this->client->get('/data-sets', $queryParams);

        return $this->parser->parseCollection($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSet(string $uuid, array $params = []): ?array
    {
        $queryParams = $this->buildSingleItemParams($params);

        try {
            $response = $this->client->get("/data-sets/{$uuid}", $queryParams);
            return $response;
        } catch (OpenApiException $e) {
            if ($e->isNotFoundError()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEquipments(array $params = []): array
    {
        $queryParams = $this->migrationHelper->convertToOpenApiParams($params);
        $response = $this->client->get('/equipments', $queryParams);

        return $this->parser->parseCollection($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getEquipment(string $uuid, array $params = []): ?array
    {
        $queryParams = $this->buildSingleItemParams($params);

        try {
            $response = $this->client->get("/equipments/{$uuid}", $queryParams);
            return $response;
        } catch (OpenApiException $e) {
            if ($e->isNotFoundError()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(): void
    {
        $this->client->clearCache();
        $this->renderingService->clearCache();
    }

    /**
     * {@inheritdoc}
     */
    public function getApiType(): string
    {
        return 'openapi';
    }

    /**
     * Build query parameters for single item requests
     *
     * @param array $params Input parameters
     * @return array OpenAPI query parameters
     */
    private function buildSingleItemParams(array $params): array
    {
        $queryParams = [];

        // Convert rendering to view parameter
        if (isset($params['rendering'])) {
            $queryParams['view'] = $this->migrationHelper->mapRendering($params['rendering']);
        } elseif (isset($params['view'])) {
            $queryParams['view'] = $params['view'];
        }

        // Add locale/language
        if (isset($params['locale'])) {
            $queryParams['locale'] = $params['locale'];
        }

        // Add fields selection
        if (isset($params['fields'])) {
            $queryParams['fields'] = is_array($params['fields'])
                ? implode(',', $params['fields'])
                : $params['fields'];
        }

        return $queryParams;
    }

    /**
     * Add batch CSL rendering to items (optimized for list views)
     *
     * Uses citeproc-php bibliography mode to render all items at once,
     * which is 5-10x faster than individual rendering.
     *
     * @param array &$items Array of research output items
     * @param string $style CSL style name (e.g., 'apa', 'mla')
     */
    private function addBatchCslRendering(array &$items, string $style): void
    {
        if (empty($items)) {
            return;
        }

        // Render all items as bibliography in one batch
        $bibliography = $this->renderingService->renderBibliography($items, $style);

        // Parse bibliography entries and assign to individual items
        $entries = $this->parseBibliographyEntries($bibliography);

        // Assign parsed entries to items
        foreach ($items as $index => &$item) {
            $item['rendering'] = $entries[$index] ?? $this->renderFallbackEntry($item);
        }
    }

    /**
     * Add individual rendering to items (for Fluid templates)
     *
     * @param array &$items Array of items
     * @param string $view View name
     */
    private function addIndividualRendering(array &$items, string $view): void
    {
        foreach ($items as &$item) {
            $item['rendering'] = $this->renderingService->renderResearchOutput($item, $view);
        }
    }

    /**
     * Parse citeproc-php bibliography HTML into individual entries
     *
     * Citeproc-php returns bibliography as:
     * <div class="csl-bib-body">
     *   <div class="csl-entry">Entry 1</div>
     *   <div class="csl-entry">Entry 2</div>
     * </div>
     *
     * @param string $bibliography Full bibliography HTML
     * @return array Array of individual citation entries
     */
    private function parseBibliographyEntries(string $bibliography): array
    {
        // Extract individual csl-entry divs
        preg_match_all('/<div class="csl-entry"[^>]*>(.*?)<\/div>/s', $bibliography, $matches);

        if (empty($matches[1])) {
            // Fallback: try without class attribute
            preg_match_all('/<div[^>]*class="csl-entry"[^>]*>(.*?)<\/div>/s', $bibliography, $matches);
        }

        return $matches[1] ?? [];
    }

    /**
     * Render fallback entry when parsing fails
     *
     * @param array $item Research output item
     * @return string Basic citation HTML
     */
    private function renderFallbackEntry(array $item): string
    {
        $title = $item['title'] ?? 'Untitled';
        $year = $item['publicationYear'] ?? '';

        return sprintf(
            '<div class="csl-entry">%s%s</div>',
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            $year ? ' (' . htmlspecialchars($year, ENT_QUOTES, 'UTF-8') . ')' : ''
        );
    }
}
