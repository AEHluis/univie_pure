<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\OpenApi;

/**
 * Migration helper for converting XML API parameters to OpenAPI format
 *
 * Provides utilities to translate between the old XML-based API structure
 * and the new OpenAPI REST parameters.
 */
class MigrationHelper
{
    /**
     * Rendering mapping from XML API to OpenAPI views
     */
    private const RENDERING_MAP = [
        'portal-short' => 'short',
        'detailsPortal' => 'detailed',
        'standard' => 'standard',
        'bibtex' => 'bibtex',
    ];

    /**
     * Convert XML API parameters to OpenAPI query parameters
     *
     * @param array $params XML API style parameters
     * @return array OpenAPI query parameters
     */
    public function convertToOpenApiParams(array $params): array
    {
        $openApiParams = [];

        // Search string mapping
        if (isset($params['search']) && !empty($params['search'])) {
            $openApiParams['q'] = $params['search'];
        }

        // Pagination: size/limit mapping
        if (isset($params['size'])) {
            $openApiParams['limit'] = (int)$params['size'];
        } elseif (isset($params['limit'])) {
            $openApiParams['limit'] = (int)$params['limit'];
        }

        // Pagination: offset mapping
        if (isset($params['offset'])) {
            $openApiParams['offset'] = (int)$params['offset'];
        }

        // Pagination: page-based (if provided)
        if (isset($params['page'])) {
            $openApiParams['page'] = (int)$params['page'];
        }

        // Sorting/Ordering mapping
        if (isset($params['sort'])) {
            $openApiParams['sort'] = $params['sort'];
        } elseif (isset($params['ordering'])) {
            $openApiParams['sort'] = $params['ordering'];
        }

        // Rendering/View mapping
        if (isset($params['rendering'])) {
            $openApiParams['view'] = $this->mapRendering($params['rendering']);
        } elseif (isset($params['view'])) {
            $openApiParams['view'] = $params['view'];
        }

        // Locale/Language mapping
        if (isset($params['locale'])) {
            $openApiParams['locale'] = $params['locale'];
        }

        // Fields selection
        if (isset($params['fields'])) {
            $openApiParams['fields'] = is_array($params['fields'])
                ? implode(',', $params['fields'])
                : $params['fields'];
        }

        // Workflow step filter (specific to research outputs)
        if (isset($params['workflowStep'])) {
            $openApiParams['workflow_status'] = $params['workflowStep'];
        }

        // Organization UUID filter
        if (isset($params['organizationUuid'])) {
            $openApiParams['organization_id'] = $params['organizationUuid'];
        }

        // Person UUID filter
        if (isset($params['personUuid'])) {
            $openApiParams['person_id'] = $params['personUuid'];
        }

        // Date range filters
        if (isset($params['startDate'])) {
            $openApiParams['start_date'] = $this->formatDate($params['startDate']);
        }

        if (isset($params['endDate'])) {
            $openApiParams['end_date'] = $this->formatDate($params['endDate']);
        }

        // Publication year filter
        if (isset($params['publicationYear'])) {
            $openApiParams['publication_year'] = (int)$params['publicationYear'];
        }

        // Include/Expand related resources
        if (isset($params['include'])) {
            $openApiParams['include'] = is_array($params['include'])
                ? implode(',', $params['include'])
                : $params['include'];
        }

        // Format specification
        if (isset($params['format'])) {
            $openApiParams['format'] = $params['format'];
        }

        return $openApiParams;
    }

    /**
     * Map XML API rendering to OpenAPI view
     *
     * @param string $rendering XML API rendering name
     * @return string OpenAPI view name
     */
    public function mapRendering(string $rendering): string
    {
        return self::RENDERING_MAP[$rendering] ?? $rendering;
    }

    /**
     * Map OpenAPI view to XML API rendering
     *
     * @param string $view OpenAPI view name
     * @return string XML API rendering name
     */
    public function mapViewToRendering(string $view): string
    {
        $reverseMap = array_flip(self::RENDERING_MAP);
        return $reverseMap[$view] ?? $view;
    }

    /**
     * Convert XML query structure to OpenAPI parameters
     *
     * Parses XML query string and extracts parameters for OpenAPI.
     *
     * @param string $xmlQuery XML query string
     * @return array OpenAPI parameters
     */
    public function convertXmlQueryToParams(string $xmlQuery): array
    {
        $params = [];

        // Parse XML
        $xml = simplexml_load_string($xmlQuery);
        if (!$xml) {
            return $params;
        }

        // Extract common fields
        if (isset($xml->searchString)) {
            $params['q'] = (string)$xml->searchString;
        }

        if (isset($xml->size)) {
            $params['limit'] = (int)$xml->size;
        }

        if (isset($xml->offset)) {
            $params['offset'] = (int)$xml->offset;
        }

        // Extract orderings
        if (isset($xml->orderings->ordering)) {
            $orderings = [];
            foreach ($xml->orderings->ordering as $ordering) {
                $orderings[] = (string)$ordering;
            }
            $params['sort'] = implode(',', $orderings);
        }

        // Extract locales
        if (isset($xml->locales->locale)) {
            $params['locale'] = (string)$xml->locales->locale[0];
        }

        // Extract renderings
        if (isset($xml->renderings->rendering)) {
            $rendering = (string)$xml->renderings->rendering[0];
            $params['view'] = $this->mapRendering($rendering);
        }

        // Extract fields
        if (isset($xml->fields->field)) {
            $fields = [];
            foreach ($xml->fields->field as $field) {
                $fields[] = (string)$field;
            }
            $params['fields'] = implode(',', $fields);
        }

        // Extract workflow steps
        if (isset($xml->workflowSteps->workflowStep)) {
            $params['workflow_status'] = (string)$xml->workflowSteps->workflowStep[0];
        }

        return $params;
    }

    /**
     * Map legacy field names to new OpenAPI field names
     *
     * Some field names may have changed between XML API and OpenAPI.
     *
     * @param array $fields Old field names
     * @return array New field names
     */
    public function mapLegacyFields(array $fields): array
    {
        $fieldMap = [
            'publicationStatuses' => 'publication_status',
            'organisationalUnits' => 'organizations',
            'relatedResearchOutputs' => 'related_outputs',
            // Add more mappings as needed
        ];

        return array_map(function ($field) use ($fieldMap) {
            // Handle wildcards (e.g., 'name.*')
            if (str_contains($field, '.*')) {
                $baseField = str_replace('.*', '', $field);
                $mappedBase = $fieldMap[$baseField] ?? $baseField;
                return $mappedBase . '.*';
            }

            return $fieldMap[$field] ?? $field;
        }, $fields);
    }

    /**
     * Format date for OpenAPI (ISO 8601)
     *
     * @param mixed $date Date in various formats
     * @return string ISO 8601 formatted date
     */
    private function formatDate($date): string
    {
        if ($date instanceof \DateTime) {
            return $date->format('Y-m-d\TH:i:s\Z');
        }

        if (is_numeric($date)) {
            return date('Y-m-d\TH:i:s\Z', (int)$date);
        }

        // Assume it's already a string, try to parse it
        $dateTime = new \DateTime($date);
        return $dateTime->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Build pagination parameters for OpenAPI
     *
     * @param int $currentPage Current page number (1-based)
     * @param int $pageSize Items per page
     * @return array Pagination parameters
     */
    public function buildPaginationParams(int $currentPage, int $pageSize): array
    {
        return [
            'limit' => $pageSize,
            'offset' => ($currentPage - 1) * $pageSize,
        ];
    }

    /**
     * Convert OpenAPI pagination response to XML API format
     *
     * For backward compatibility when migrating.
     *
     * @param array $openApiResponse OpenAPI paginated response
     * @return array XML API style response
     */
    public function convertPaginationResponse(array $openApiResponse): array
    {
        $items = $openApiResponse['items'] ?? [];
        $pagination = $openApiResponse['pagination'] ?? [];

        return [
            'items' => $items,
            'count' => $pagination['total'] ?? count($items),
            'pageInformation' => [
                'offset' => $pagination['offset'] ?? 0,
                'size' => $pagination['limit'] ?? 20,
            ],
        ];
    }
}
