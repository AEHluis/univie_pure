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
     * {@inheritdoc}
     */
    public function getPersonsByUuids(array $uuids, array $params = []): array
    {
        if (empty($uuids)) {
            return ['items' => [], 'count' => 0];
        }

        // Fetch all persons in a single bulk query using comma-separated UUIDs
        // Most Pure XML APIs support filtering by comma-separated UUIDs
        $uuidFilter = implode(',', array_filter($uuids));

        $xml = $this->buildPersonsBulkQuery($uuidFilter, $params);
        $response = $this->webService->getJson('persons', $xml);

        return $this->normalizeResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getProjectsByUuids(array $uuids, array $params = []): array
    {
        if (empty($uuids)) {
            return ['items' => [], 'count' => 0];
        }

        $uuidFilter = implode(',', array_filter($uuids));

        $xml = $this->buildProjectsBulkQuery($uuidFilter, $params);
        $response = $this->webService->getJson('projects', $xml);

        return $this->normalizeResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrganisationalUnitsByUuids(array $uuids, array $params = []): array
    {
        if (empty($uuids)) {
            return ['items' => [], 'count' => 0];
        }

        $uuidFilter = implode(',', array_filter($uuids));

        $xml = $this->buildOrganisationalUnitsBulkQuery($uuidFilter, $params);
        $response = $this->webService->getJson('organisational-units', $xml);

        return $this->normalizeResponse($response);
    }

    /**
     * Build XML query for bulk persons by UUIDs
     */
    private function buildPersonsBulkQuery(string $uuidFilter, array $params): string
    {
        $size = count(explode(',', $uuidFilter));
        $locale = $params['locale'] ?? 'de_DE';
        $fields = $params['fields'] ?? ['uuid', 'name.*', 'honoraryStaffOrganisationAssociations.*'];
        $rendering = $params['rendering'] ?? 'standard';

        // Build XML with UUID filter
        // The Pure XML API typically supports <uuids> element for filtering
        $xml = '<?xml version="1.0"?>';
        $xml .= '<personsQuery>';
        $xml .= '<uuids>' . htmlspecialchars($uuidFilter, ENT_XML1, 'UTF-8') . '</uuids>';
        $xml .= '<size>' . $size . '</size>';
        $xml .= '<offset>0</offset>';
        $xml .= '<linkingStrategy>noLinkingStrategy</linkingStrategy>';
        $xml .= '<locales><locale>' . htmlspecialchars($locale, ENT_XML1, 'UTF-8') . '</locale></locales>';
        $xml .= '<renderings><rendering>' . htmlspecialchars($rendering, ENT_XML1, 'UTF-8') . '</rendering></renderings>';
        $xml .= '<fields>';
        foreach ($fields as $field) {
            $xml .= '<field>' . htmlspecialchars($field, ENT_XML1, 'UTF-8') . '</field>';
        }
        $xml .= '</fields>';
        $xml .= '</personsQuery>';

        return $xml;
    }

    /**
     * Build XML query for bulk projects by UUIDs
     */
    private function buildProjectsBulkQuery(string $uuidFilter, array $params): string
    {
        $size = count(explode(',', $uuidFilter));
        $locale = $params['locale'] ?? 'de_DE';
        $fields = $params['fields'] ?? ['uuid', 'title.*', 'acronym'];
        $rendering = $params['rendering'] ?? 'standard';

        $xml = '<?xml version="1.0"?>';
        $xml .= '<projectsQuery>';
        $xml .= '<uuids>' . htmlspecialchars($uuidFilter, ENT_XML1, 'UTF-8') . '</uuids>';
        $xml .= '<size>' . $size . '</size>';
        $xml .= '<offset>0</offset>';
        $xml .= '<linkingStrategy>noLinkingStrategy</linkingStrategy>';
        $xml .= '<locales><locale>' . htmlspecialchars($locale, ENT_XML1, 'UTF-8') . '</locale></locales>';
        $xml .= '<renderings><rendering>' . htmlspecialchars($rendering, ENT_XML1, 'UTF-8') . '</rendering></renderings>';
        $xml .= '<fields>';
        foreach ($fields as $field) {
            $xml .= '<field>' . htmlspecialchars($field, ENT_XML1, 'UTF-8') . '</field>';
        }
        $xml .= '</fields>';
        $xml .= '</projectsQuery>';

        return $xml;
    }

    /**
     * Build XML query for bulk organisational units by UUIDs
     */
    private function buildOrganisationalUnitsBulkQuery(string $uuidFilter, array $params): string
    {
        $size = count(explode(',', $uuidFilter));
        $locale = $params['locale'] ?? 'de_DE';
        $fields = $params['fields'] ?? ['uuid', 'name.*'];
        $rendering = $params['rendering'] ?? 'standard';

        $xml = '<?xml version="1.0"?>';
        $xml .= '<organisationalUnitsQuery>';
        $xml .= '<uuids>' . htmlspecialchars($uuidFilter, ENT_XML1, 'UTF-8') . '</uuids>';
        $xml .= '<size>' . $size . '</size>';
        $xml .= '<offset>0</offset>';
        $xml .= '<linkingStrategy>noLinkingStrategy</linkingStrategy>';
        $xml .= '<locales><locale>' . htmlspecialchars($locale, ENT_XML1, 'UTF-8') . '</locale></locales>';
        $xml .= '<renderings><rendering>' . htmlspecialchars($rendering, ENT_XML1, 'UTF-8') . '</rendering></renderings>';
        $xml .= '<fields>';
        foreach ($fields as $field) {
            $xml .= '<field>' . htmlspecialchars($field, ENT_XML1, 'UTF-8') . '</field>';
        }
        $xml .= '</fields>';
        $xml .= '</organisationalUnitsQuery>';

        return $xml;
    }

    /**
     * Build XML query for persons endpoint
     */
    private function buildPersonsQuery(array $params): string
    {
        // Support both 'search' and 'searchString' parameters
        $searchString = $params['searchString'] ?? $params['search'] ?? '';
        $size = $params['limit'] ?? $params['size'] ?? 20;
        $offset = $params['offset'] ?? 0;
        $ordering = $params['sort'] ?? $params['ordering'] ?? '-startDate';
        $fields = $params['fields'] ?? ['uuid', 'name.*', 'renderings.*'];
        $locale = $params['locale'] ?? 'de_DE';
        $rendering = $params['rendering'] ?? $params['view'] ?? 'portal-short';
        $employmentStatus = $params['employmentStatus'] ?? null;

        $additionalParams = [];
        if ($employmentStatus !== null) {
            $additionalParams['employmentStatus'] = $employmentStatus;
        }

        return $this->buildXmlQuery(
            'personsQuery',
            $searchString,
            $size,
            $offset,
            $fields,
            [$ordering],
            [$locale],
            [$rendering],
            [],
            $additionalParams
        );
    }

    /**
     * Build XML query for research-outputs endpoint
     */
    private function buildResearchOutputsQuery(array $params): string
    {
        // Support both 'search' and 'searchString' parameters
        $searchString = $params['searchString'] ?? $params['search'] ?? '';
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
        // Support both 'search' and 'searchString' parameters
        $searchString = $params['searchString'] ?? $params['search'] ?? '';
        $size = $params['limit'] ?? $params['size'] ?? 20;
        $offset = $params['offset'] ?? 0;
        $ordering = $params['sort'] ?? $params['ordering'] ?? '-startDate';
        $fields = $params['fields'] ?? ['uuid', 'renderings.*'];
        $locale = $params['locale'] ?? 'de_DE';
        $rendering = $params['rendering'] ?? $params['view'] ?? 'portal-short';

        // Support workflowSteps parameter (array or single value)
        $workflowSteps = [];
        if (isset($params['workflowSteps'])) {
            $workflowSteps = is_array($params['workflowSteps']) ? $params['workflowSteps'] : [$params['workflowSteps']];
        }

        return $this->buildXmlQuery(
            'projectsQuery',
            $searchString,
            $size,
            $offset,
            $fields,
            [$ordering],
            [$locale],
            [$rendering],
            $workflowSteps
        );
    }

    /**
     * Build XML query for organisational-units endpoint
     */
    private function buildOrganisationalUnitsQuery(array $params): string
    {
        // Support both 'search' and 'searchString' parameters
        $searchString = $params['searchString'] ?? $params['search'] ?? '';
        $size = $params['limit'] ?? $params['size'] ?? 20;
        $offset = $params['offset'] ?? 0;
        $fields = $params['fields'] ?? ['uuid', 'name.*'];
        $locale = $params['locale'] ?? 'de_DE';
        $rendering = $params['rendering'] ?? $params['view'] ?? 'standard';
        $ordering = $params['ordering'] ?? null;

        $additionalParams = [];
        if (isset($params['returnUsedContent'])) {
            $additionalParams['returnUsedContent'] = $params['returnUsedContent'];
        }

        $orderings = [];
        if ($ordering !== null) {
            $orderings = is_array($ordering) ? $ordering : [$ordering];
        }

        return $this->buildXmlQuery(
            'organisationalUnitsQuery',
            $searchString,
            $size,
            $offset,
            $fields,
            $orderings,
            [$locale],
            [$rendering],
            [],
            $additionalParams
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
     * @param array $additionalParams Additional parameters (employmentStatus, returnUsedContent, etc.)
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
        array $workflowSteps = [],
        array $additionalParams = []
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

        // Add workflow steps (for research outputs and projects)
        if (!empty($workflowSteps)) {
            $xml .= '<workflowSteps>';
            foreach ($workflowSteps as $step) {
                $xml .= '<workflowStep>' . htmlspecialchars($step, ENT_XML1, 'UTF-8') . '</workflowStep>';
            }
            $xml .= '</workflowSteps>';
        }

        // Add additional parameters (employmentStatus, returnUsedContent, etc.)
        foreach ($additionalParams as $key => $value) {
            if (is_bool($value)) {
                $xml .= '<' . $key . '>' . ($value ? 'true' : 'false') . '</' . $key . '>';
            } elseif (is_string($value)) {
                $xml .= '<' . $key . '>' . htmlspecialchars($value, ENT_XML1, 'UTF-8') . '</' . $key . '>';
            }
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
