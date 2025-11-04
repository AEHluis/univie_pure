<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

/**
 * XML API service wrapper
 *
 * Wraps the existing WebService to implement ApiServiceInterface.
 * This allows the XML API to be used through the same interface as OpenAPI.
 */
class XmlApiService implements ApiServiceInterface
{
    public function __construct(
        private readonly WebService $webService
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getPersons(array $params = []): array
    {
        $xml = $this->buildPersonsQuery($params);
        $response = $this->webService->getJson('persons', $xml);

        return $this->normalizeResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getPerson(string $uuid, array $params = []): ?array
    {
        $rendering = $params['rendering'] ?? 'detailsPortal';
        $locale = $params['locale'] ?? 'de_DE';

        $person = $this->webService->getSingleResponse(
            'persons',
            $uuid,
            $locale,
            'json',
            $rendering
        );

        return $person ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getResearchOutputs(array $params = []): array
    {
        $xml = $this->buildResearchOutputsQuery($params);
        $response = $this->webService->getJson('research-outputs', $xml);

        return $this->normalizeResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getResearchOutput(string $uuid, array $params = []): ?array
    {
        $rendering = $params['rendering'] ?? 'detailsPortal';
        $locale = $params['locale'] ?? 'de_DE';

        $output = $this->webService->getSingleResponse(
            'research-outputs',
            $uuid,
            $locale,
            'json',
            $rendering
        );

        return $output ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getResearchOutputBibtex(string $uuid, array $params = []): ?string
    {
        $bibtex = $this->webService->getSingleResponse(
            'research-outputs',
            $uuid,
            'de_DE',
            'xml',
            'bibtex'
        );

        return $bibtex ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getProjects(array $params = []): array
    {
        $xml = $this->buildProjectsQuery($params);
        $response = $this->webService->getJson('projects', $xml);

        return $this->normalizeResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getProject(string $uuid, array $params = []): ?array
    {
        $rendering = $params['rendering'] ?? 'detailsPortal';
        $locale = $params['locale'] ?? 'de_DE';

        $project = $this->webService->getSingleResponse(
            'projects',
            $uuid,
            $locale,
            'json',
            $rendering
        );

        return $project ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrganisationalUnits(array $params = []): array
    {
        $xml = $this->buildOrganisationalUnitsQuery($params);
        $response = $this->webService->getJson('organisational-units', $xml);

        return $this->normalizeResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrganisationalUnit(string $uuid, array $params = []): ?array
    {
        $rendering = $params['rendering'] ?? 'detailsPortal';
        $locale = $params['locale'] ?? 'de_DE';

        $unit = $this->webService->getSingleResponse(
            'organisational-units',
            $uuid,
            $locale,
            'json',
            $rendering
        );

        return $unit ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSets(array $params = []): array
    {
        $xml = $this->buildDataSetsQuery($params);
        $response = $this->webService->getJson('data-sets', $xml);

        return $this->normalizeResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSet(string $uuid, array $params = []): ?array
    {
        $rendering = $params['rendering'] ?? 'detailsPortal';
        $locale = $params['locale'] ?? 'de_DE';

        $dataSet = $this->webService->getSingleResponse(
            'data-sets',
            $uuid,
            $locale,
            'json',
            $rendering
        );

        return $dataSet ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getEquipments(array $params = []): array
    {
        $xml = $this->buildEquipmentsQuery($params);
        $response = $this->webService->getJson('equipments', $xml);

        return $this->normalizeResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getEquipment(string $uuid, array $params = []): ?array
    {
        $rendering = $params['rendering'] ?? 'detailsPortal';
        $locale = $params['locale'] ?? 'de_DE';

        $equipment = $this->webService->getSingleResponse(
            'equipments',
            $uuid,
            $locale,
            'json',
            $rendering
        );

        return $equipment ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(): void
    {
        // WebService cache clearing would go here if implemented
    }

    /**
     * {@inheritdoc}
     */
    public function getApiType(): string
    {
        return 'xml';
    }

    /**
     * Build XML query for persons endpoint
     */
    private function buildPersonsQuery(array $params): string
    {
        $searchString = $params['search'] ?? '';
        $size = $params['limit'] ?? $params['size'] ?? 20;
        $offset = $params['offset'] ?? 0;
        $ordering = $params['sort'] ?? $params['ordering'] ?? '-startDate';
        $fields = $params['fields'] ?? ['uuid', 'name.*', 'renderings.*'];
        $locale = $params['locale'] ?? 'de_DE';
        $rendering = $params['rendering'] ?? $params['view'] ?? 'portal-short';

        return $this->buildXmlQuery(
            'personsQuery',
            $searchString,
            $size,
            $offset,
            $fields,
            [$ordering],
            [$locale],
            [$rendering]
        );
    }

    /**
     * Build XML query for research-outputs endpoint
     */
    private function buildResearchOutputsQuery(array $params): string
    {
        $searchString = $params['search'] ?? '';
        $size = $params['limit'] ?? $params['size'] ?? 20;
        $offset = $params['offset'] ?? 0;
        $ordering = $params['sort'] ?? $params['ordering'] ?? '-publicationYear';
        $fields = $params['fields'] ?? ['uuid', 'renderings.*', 'publicationStatuses.*'];
        $locale = $params['locale'] ?? 'de_DE';
        $rendering = $params['rendering'] ?? $params['view'] ?? 'portal-short';
        $workflowStep = $params['workflowStep'] ?? 'approved';

        return $this->buildXmlQuery(
            'researchOutputsQuery',
            $searchString,
            $size,
            $offset,
            $fields,
            [$ordering],
            [$locale],
            [$rendering],
            [$workflowStep]
        );
    }

    /**
     * Build XML query for projects endpoint
     */
    private function buildProjectsQuery(array $params): string
    {
        $searchString = $params['search'] ?? '';
        $size = $params['limit'] ?? $params['size'] ?? 20;
        $offset = $params['offset'] ?? 0;
        $ordering = $params['sort'] ?? $params['ordering'] ?? '-startDate';
        $fields = $params['fields'] ?? ['uuid', 'renderings.*'];
        $locale = $params['locale'] ?? 'de_DE';
        $rendering = $params['rendering'] ?? $params['view'] ?? 'portal-short';

        return $this->buildXmlQuery(
            'projectsQuery',
            $searchString,
            $size,
            $offset,
            $fields,
            [$ordering],
            [$locale],
            [$rendering]
        );
    }

    /**
     * Build XML query for organisational-units endpoint
     */
    private function buildOrganisationalUnitsQuery(array $params): string
    {
        $searchString = $params['search'] ?? '';
        $size = $params['limit'] ?? $params['size'] ?? 20;
        $offset = $params['offset'] ?? 0;
        $fields = $params['fields'] ?? ['uuid', 'name.*'];
        $locale = $params['locale'] ?? 'de_DE';
        $rendering = $params['rendering'] ?? $params['view'] ?? 'standard';

        return $this->buildXmlQuery(
            'organisationalUnitsQuery',
            $searchString,
            $size,
            $offset,
            $fields,
            [],
            [$locale],
            [$rendering]
        );
    }

    /**
     * Build XML query for data-sets endpoint
     */
    private function buildDataSetsQuery(array $params): string
    {
        $searchString = $params['search'] ?? '';
        $size = $params['limit'] ?? $params['size'] ?? 20;
        $offset = $params['offset'] ?? 0;
        $fields = $params['fields'] ?? ['uuid', 'renderings.*'];
        $locale = $params['locale'] ?? 'de_DE';
        $rendering = $params['rendering'] ?? $params['view'] ?? 'portal-short';

        return $this->buildXmlQuery(
            'dataSetsQuery',
            $searchString,
            $size,
            $offset,
            $fields,
            [],
            [$locale],
            [$rendering]
        );
    }

    /**
     * Build XML query for equipments endpoint
     */
    private function buildEquipmentsQuery(array $params): string
    {
        $searchString = $params['search'] ?? '';
        $size = $params['limit'] ?? $params['size'] ?? 20;
        $offset = $params['offset'] ?? 0;
        $fields = $params['fields'] ?? ['uuid', 'renderings.*'];
        $locale = $params['locale'] ?? 'de_DE';
        $rendering = $params['rendering'] ?? $params['view'] ?? 'portal-short';

        return $this->buildXmlQuery(
            'equipmentsQuery',
            $searchString,
            $size,
            $offset,
            $fields,
            [],
            [$locale],
            [$rendering]
        );
    }

    /**
     * Generic XML query builder
     *
     * @param string $queryType Root element name (e.g., 'personsQuery', 'researchOutputsQuery')
     * @param string $searchString Search term
     * @param int $size Page size
     * @param int $offset Offset for pagination
     * @param array $fields Fields to retrieve
     * @param array $orderings Sort orders
     * @param array $locales Locales
     * @param array $renderings Rendering styles
     * @param array $workflowSteps Workflow steps (optional)
     * @return string XML query string
     */
    private function buildXmlQuery(
        string $queryType,
        string $searchString,
        int $size,
        int $offset,
        array $fields,
        array $orderings = [],
        array $locales = ['de_DE'],
        array $renderings = ['portal-short'],
        array $workflowSteps = []
    ): string {
        $xml = '<?xml version="1.0"?>';
        $xml .= '<' . $queryType . '>';

        // Add search string if provided
        if (!empty($searchString)) {
            $xml .= '<searchString>' . htmlspecialchars($searchString, ENT_XML1, 'UTF-8') . '</searchString>';
        }

        // Add pagination
        $xml .= '<size>' . (int)$size . '</size>';
        $xml .= '<offset>' . (int)$offset . '</offset>';

        // Add linking strategy
        $xml .= '<linkingStrategy>noLinkingStrategy</linkingStrategy>';

        // Add locales
        if (!empty($locales)) {
            $xml .= '<locales>';
            foreach ($locales as $locale) {
                $xml .= '<locale>' . htmlspecialchars($locale, ENT_XML1, 'UTF-8') . '</locale>';
            }
            $xml .= '</locales>';
        }

        // Add renderings
        if (!empty($renderings)) {
            $xml .= '<renderings>';
            foreach ($renderings as $rendering) {
                $xml .= '<rendering>' . htmlspecialchars($rendering, ENT_XML1, 'UTF-8') . '</rendering>';
            }
            $xml .= '</renderings>';
        }

        // Add fields
        if (!empty($fields)) {
            $xml .= '<fields>';
            foreach ($fields as $field) {
                $xml .= '<field>' . htmlspecialchars($field, ENT_XML1, 'UTF-8') . '</field>';
            }
            $xml .= '</fields>';
        }

        // Add orderings
        if (!empty($orderings)) {
            $xml .= '<orderings>';
            foreach ($orderings as $ordering) {
                $xml .= '<ordering>' . htmlspecialchars($ordering, ENT_XML1, 'UTF-8') . '</ordering>';
            }
            $xml .= '</orderings>';
        }

        // Add workflow steps (for research outputs)
        if (!empty($workflowSteps)) {
            $xml .= '<workflowSteps>';
            foreach ($workflowSteps as $step) {
                $xml .= '<workflowStep>' . htmlspecialchars($step, ENT_XML1, 'UTF-8') . '</workflowStep>';
            }
            $xml .= '</workflowSteps>';
        }

        $xml .= '</' . $queryType . '>';

        return $xml;
    }

    /**
     * Normalize XML API response to match OpenAPI format
     */
    private function normalizeResponse(array $response): array
    {
        // If response has 'items', it's already normalized
        if (isset($response['items'])) {
            return $response;
        }

        // Extract items from various possible keys
        $items = $response['result'] ?? $response;

        return [
            'items' => is_array($items) ? array_values($items) : [$items],
            'count' => $response['count'] ?? count($items),
            'pagination' => [
                'offset' => $response['pageInformation']['offset'] ?? 0,
                'size' => $response['pageInformation']['size'] ?? 20,
            ],
        ];
    }
}
