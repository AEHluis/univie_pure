<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\OpenApi;

use Univie\UniviePure\Endpoint\DataSetEndpoint;
use Univie\UniviePure\Endpoint\EquipmentEndpoint;
use Univie\UniviePure\Endpoint\OrganizationalUnitEndpoint;
use Univie\UniviePure\Endpoint\PersonEndpoint;
use Univie\UniviePure\Endpoint\ProjectEndpoint;
use Univie\UniviePure\Endpoint\ResearchOutputEndpoint;
use Univie\UniviePure\Service\ApiServiceInterface;
use Univie\UniviePure\Service\RenderingService;
use Psr\Log\LoggerInterface;

/**
 * OpenAPI service implementation
 *
 * Implements ApiServiceInterface using the new Pure OpenAPI (REST) endpoints.
 * Delegates to specialized endpoint classes for each entity type.
 */
class OpenApiService implements ApiServiceInterface
{
    private PersonEndpoint $personEndpoint;
    private ResearchOutputEndpoint $researchOutputEndpoint;
    private ProjectEndpoint $projectEndpoint;
    private OrganizationalUnitEndpoint $organizationalUnitEndpoint;
    private DataSetEndpoint $dataSetEndpoint;
    private EquipmentEndpoint $equipmentEndpoint;

    public function __construct(
        private readonly OpenApiClient $client,
        private readonly OpenApiResponseParser $parser,
        private readonly RenderingService $renderingService,
        private readonly LoggerInterface $logger
    ) {
        // Initialize endpoint instances
        $this->personEndpoint = new PersonEndpoint(
            $this->client,
            $this->parser,
            $this->renderingService,
            $this->logger
        );

        $this->researchOutputEndpoint = new ResearchOutputEndpoint(
            $this->client,
            $this->parser,
            $this->renderingService,
            $this->logger
        );

        $this->projectEndpoint = new ProjectEndpoint(
            $this->client,
            $this->parser,
            $this->renderingService,
            $this->logger
        );

        $this->organizationalUnitEndpoint = new OrganizationalUnitEndpoint(
            $this->client,
            $this->parser,
            $this->renderingService,
            $this->logger
        );

        $this->dataSetEndpoint = new DataSetEndpoint(
            $this->client,
            $this->parser,
            $this->renderingService,
            $this->logger
        );

        $this->equipmentEndpoint = new EquipmentEndpoint(
            $this->client,
            $this->parser,
            $this->renderingService,
            $this->logger
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPersons(array $params = []): array
    {
        return $this->personEndpoint->getAll($params);
    }

    /**
     * {@inheritdoc}
     */
    public function getPerson(string $uuid, array $params = []): ?array
    {
        return $this->personEndpoint->getOne($uuid, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getPersonsByUuids(array $uuids, array $params = []): array
    {
        return $this->personEndpoint->getByUuids($uuids, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getResearchOutputs(array $params = []): array
    {
        return $this->researchOutputEndpoint->getAll($params);
    }

    /**
     * {@inheritdoc}
     */
    public function getResearchOutput(string $uuid, array $params = []): ?array
    {
        return $this->researchOutputEndpoint->getOne($uuid, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getResearchOutputBibtex(string $uuid, array $params = []): ?string
    {
        return $this->researchOutputEndpoint->getBibtex($uuid, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getProjects(array $params = []): array
    {
        return $this->projectEndpoint->getAll($params);
    }

    /**
     * {@inheritdoc}
     */
    public function getProject(string $uuid, array $params = []): ?array
    {
        return $this->projectEndpoint->getOne($uuid, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getProjectsByUuids(array $uuids, array $params = []): array
    {
        return $this->projectEndpoint->getByUuids($uuids, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrganisationalUnits(array $params = []): array
    {
        return $this->organizationalUnitEndpoint->getAll($params);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrganisationalUnit(string $uuid, array $params = []): ?array
    {
        return $this->organizationalUnitEndpoint->getOne($uuid, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrganisationalUnitsByUuids(array $uuids, array $params = []): array
    {
        return $this->organizationalUnitEndpoint->getByUuids($uuids, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSets(array $params = []): array
    {
        return $this->dataSetEndpoint->getAll($params);
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSet(string $uuid, array $params = []): ?array
    {
        return $this->dataSetEndpoint->getOne($uuid, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getEquipments(array $params = []): array
    {
        return $this->equipmentEndpoint->getAll($params);
    }

    /**
     * {@inheritdoc}
     */
    public function getEquipment(string $uuid, array $params = []): ?array
    {
        return $this->equipmentEndpoint->getOne($uuid, $params);
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
}
