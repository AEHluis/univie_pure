<?php

declare(strict_types=1);

namespace Univie\UniviePure\Endpoint;

use Univie\UniviePure\Service\OpenApi\OpenApiClient;
use Univie\UniviePure\Service\OpenApi\OpenApiException;
use Univie\UniviePure\Service\OpenApi\OpenApiResponseParser;
use Univie\UniviePure\Service\RenderingService;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for Pure API endpoints
 *
 * Provides common functionality for all endpoint implementations.
 */
abstract class AbstractEndpoint
{
    /**
     * Rendering/view name mapping for backward compatibility
     */
    private const VIEW_MAP = [
        'portal-short' => 'short',
        'detailsPortal' => 'detailed',
        'standard' => 'standard',
        'bibtex' => 'bibtex',
    ];

    public function __construct(
        protected readonly OpenApiClient $client,
        protected readonly OpenApiResponseParser $parser,
        protected readonly RenderingService $renderingService,
        protected readonly LoggerInterface $logger
    ) {}

    /**
     * Get the API endpoint path (e.g., '/persons', '/research-outputs')
     */
    abstract protected function getEndpointPath(): string;

    /**
     * Get the default view for list items
     */
    protected function getDefaultListView(): string
    {
        return 'short';
    }

    /**
     * Get the default view for single items
     */
    protected function getDefaultDetailView(): string
    {
        return 'detailed';
    }

    /**
     * Render a single item
     *
     * @param array $item Item data
     * @param string $view View name
     * @return string Rendered HTML
     */
    abstract protected function renderItem(array $item, string $view): string;

    /**
     * Convert input parameters to OpenAPI query parameters
     *
     * @param array $params Input parameters
     * @return array OpenAPI query parameters
     */
    protected function buildQueryParams(array $params): array
    {
        $queryParams = [];

        // Search: 'search' → 'q'
        if (!empty($params['search'])) {
            $queryParams['q'] = $params['search'];
        } elseif (!empty($params['q'])) {
            $queryParams['q'] = $params['q'];
        }

        // Pagination: 'limit' → 'size'
        if (isset($params['size'])) {
            $queryParams['size'] = (int)$params['size'];
        } elseif (isset($params['limit'])) {
            $queryParams['size'] = (int)$params['limit'];
        }

        // Offset: direct pass-through
        if (isset($params['offset'])) {
            $queryParams['offset'] = (int)$params['offset'];
        }

        // Sorting
        if (isset($params['sort'])) {
            $queryParams['sort'] = $params['sort'];
        }

        // View/rendering
        if (isset($params['rendering'])) {
            $queryParams['view'] = $this->mapView($params['rendering']);
        } elseif (isset($params['view'])) {
            $queryParams['view'] = $this->mapView($params['view']);
        }

        // Locale
        if (isset($params['locale'])) {
            $queryParams['locale'] = $params['locale'];
        }

        // Fields selection
        if (isset($params['fields'])) {
            $queryParams['fields'] = is_array($params['fields'])
                ? implode(',', $params['fields'])
                : $params['fields'];
        }

        return $queryParams;
    }

    /**
     * Build query parameters for single item requests
     *
     * @param array $params Input parameters
     * @return array OpenAPI query parameters
     */
    protected function buildSingleItemParams(array $params): array
    {
        $queryParams = [];

        if (isset($params['rendering'])) {
            $queryParams['view'] = $this->mapView($params['rendering']);
        } elseif (isset($params['view'])) {
            $queryParams['view'] = $this->mapView($params['view']);
        }

        if (isset($params['locale'])) {
            $queryParams['locale'] = $params['locale'];
        }

        if (isset($params['fields'])) {
            $queryParams['fields'] = is_array($params['fields'])
                ? implode(',', $params['fields'])
                : $params['fields'];
        }

        return $queryParams;
    }

    /**
     * Map view/rendering name to OpenAPI view
     *
     * @param string $view View or rendering name
     * @return string OpenAPI view name
     */
    protected function mapView(string $view): string
    {
        return self::VIEW_MAP[$view] ?? $view;
    }

    /**
     * Normalize collection response to match expected format
     *
     * @param array $collection Parser collection output
     * @return array Normalized response
     */
    protected function normalizeCollectionResponse(array $collection): array
    {
        return [
            'items' => $collection['items'] ?? [],
            'count' => $collection['pagination']['total'] ?? count($collection['items'] ?? []),
            'pagination' => [
                'offset' => $collection['pagination']['offset'] ?? 0,
                'size' => $collection['pagination']['size'] ?? 20,
            ],
        ];
    }

    /**
     * Get multiple items
     *
     * @param array $params Query parameters
     * @return array Collection response with items and pagination
     */
    public function getAll(array $params = []): array
    {
        $queryParams = $this->buildQueryParams($params);
        $response = $this->client->get($this->getEndpointPath(), $queryParams);
        $collection = $this->parser->parseCollection($response);

        $view = $params['view'] ?? $params['rendering'] ?? $this->getDefaultListView();
        $view = $this->mapView($view);

        foreach ($collection['items'] as &$item) {
            $item['rendering'] = $this->renderItem($item, $view);
        }
        unset($item);

        return $this->normalizeCollectionResponse($collection);
    }

    /**
     * Get a single item by UUID
     *
     * @param string $uuid Item UUID
     * @param array $params Query parameters
     * @return array|null Item data or null if not found
     */
    public function getOne(string $uuid, array $params = []): ?array
    {
        $queryParams = $this->buildSingleItemParams($params);

        try {
            $response = $this->client->get("{$this->getEndpointPath()}/{$uuid}", $queryParams);

            $view = $params['view'] ?? $params['rendering'] ?? $this->getDefaultDetailView();
            $view = $this->mapView($view);
            $response['rendering'] = $this->renderItem($response, $view);

            return $response;
        } catch (OpenApiException $e) {
            if ($e->isNotFoundError()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Get multiple items by UUIDs
     *
     * @param array $uuids Array of UUIDs
     * @param array $params Query parameters
     * @return array Collection response
     */
    public function getByUuids(array $uuids, array $params = []): array
    {
        if (empty($uuids)) {
            return ['items' => [], 'count' => 0];
        }

        $queryParams = $this->buildQueryParams($params);
        $queryParams['ids'] = implode(',', array_filter($uuids));
        $queryParams['size'] = count($uuids);

        $response = $this->client->get($this->getEndpointPath(), $queryParams);
        $collection = $this->parser->parseCollection($response);

        $view = $params['view'] ?? $params['rendering'] ?? $this->getDefaultListView();
        $view = $this->mapView($view);

        foreach ($collection['items'] as &$item) {
            $item['rendering'] = $this->renderItem($item, $view);
        }
        unset($item);

        return $this->normalizeCollectionResponse($collection);
    }
}
