<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

/**
 * Interface for Pure API service implementations
 *
 * Provides a unified interface for both XML-based and OpenAPI implementations.
 * This allows seamless migration between API versions with feature flag toggles.
 */
interface ApiServiceInterface
{
    /**
     * Get list of persons with optional filters
     *
     * @param array $params Query parameters (search, filters, pagination, etc.)
     * @return array Array of person data
     */
    public function getPersons(array $params = []): array;

    /**
     * Get single person by UUID/ID
     *
     * @param string $uuid Person UUID or ID
     * @param array $params Additional parameters (rendering, fields, etc.)
     * @return array|null Person data or null if not found
     */
    public function getPerson(string $uuid, array $params = []): ?array;

    /**
     * Get list of research outputs (publications)
     *
     * @param array $params Query parameters (search, filters, pagination, etc.)
     * @return array Array of research output data
     */
    public function getResearchOutputs(array $params = []): array;

    /**
     * Get single research output by UUID/ID
     *
     * @param string $uuid Research output UUID or ID
     * @param array $params Additional parameters (rendering, fields, etc.)
     * @return array|null Research output data or null if not found
     */
    public function getResearchOutput(string $uuid, array $params = []): ?array;

    /**
     * Get research output in BibTeX format
     *
     * @param string $uuid Research output UUID or ID
     * @param array $params Additional parameters
     * @return string|null BibTeX formatted string or null if not found
     */
    public function getResearchOutputBibtex(string $uuid, array $params = []): ?string;

    /**
     * Get list of projects
     *
     * @param array $params Query parameters (search, filters, pagination, etc.)
     * @return array Array of project data
     */
    public function getProjects(array $params = []): array;

    /**
     * Get single project by UUID/ID
     *
     * @param string $uuid Project UUID or ID
     * @param array $params Additional parameters (rendering, fields, etc.)
     * @return array|null Project data or null if not found
     */
    public function getProject(string $uuid, array $params = []): ?array;

    /**
     * Get list of organisational units
     *
     * @param array $params Query parameters (search, filters, pagination, etc.)
     * @return array Array of organisational unit data
     */
    public function getOrganisationalUnits(array $params = []): array;

    /**
     * Get single organisational unit by UUID/ID
     *
     * @param string $uuid Organisational unit UUID or ID
     * @param array $params Additional parameters (rendering, fields, etc.)
     * @return array|null Organisational unit data or null if not found
     */
    public function getOrganisationalUnit(string $uuid, array $params = []): ?array;

    /**
     * Get list of data sets
     *
     * @param array $params Query parameters (search, filters, pagination, etc.)
     * @return array Array of data set data
     */
    public function getDataSets(array $params = []): array;

    /**
     * Get single data set by UUID/ID
     *
     * @param string $uuid Data set UUID or ID
     * @param array $params Additional parameters (rendering, fields, etc.)
     * @return array|null Data set data or null if not found
     */
    public function getDataSet(string $uuid, array $params = []): ?array;

    /**
     * Get list of equipments
     *
     * @param array $params Query parameters (search, filters, pagination, etc.)
     * @return array Array of equipment data
     */
    public function getEquipments(array $params = []): array;

    /**
     * Get single equipment by UUID/ID
     *
     * @param string $uuid Equipment UUID or ID
     * @param array $params Additional parameters (rendering, fields, etc.)
     * @return array|null Equipment data or null if not found
     */
    public function getEquipment(string $uuid, array $params = []): ?array;

    /**
     * Clear all cached data
     *
     * @return void
     */
    public function clearCache(): void;

    /**
     * Get API type identifier
     *
     * @return string 'xml' or 'openapi'
     */
    public function getApiType(): string;
}
