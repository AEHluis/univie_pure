<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\Cache;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use Psr\Log\LoggerInterface;

/**
 * Unified Cache Manager
 *
 * Provides consistent caching strategy across all Pure services (API, Rendering, CSL).
 * Uses TYPO3 cache tags for selective clearing and prevents stale content cascades.
 */
class UnifiedCacheManager
{
    /**
     * Cache layer prefixes
     */
    public const LAYER_XML = 'xml';
    public const LAYER_OPENAPI = 'openapi';
    public const LAYER_RENDER = 'render';
    public const LAYER_CSL = 'csl';

    /**
     * Resource types (matching API endpoints)
     */
    public const RESOURCE_PERSON = 'person';
    public const RESOURCE_RESEARCH_OUTPUT = 'research_output';
    public const RESOURCE_PROJECT = 'project';
    public const RESOURCE_ORGANISATION = 'organisation';
    public const RESOURCE_DATASET = 'dataset';
    public const RESOURCE_EQUIPMENT = 'equipment';

    /**
     * Cache tag prefixes
     */
    private const TAG_API = 'pure_api';
    private const TAG_API_XML = 'pure_api_xml';
    private const TAG_API_OPENAPI = 'pure_api_openapi';
    private const TAG_RENDERING = 'pure_rendering';
    private const TAG_CSL = 'pure_csl';
    private const TAG_RESOURCE = 'pure_%s_%s'; // pure_person_abc123
    private const TAG_TYPE = 'pure_type_%s'; // pure_type_person

    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Generate consistent cache key
     *
     * Format: {layer}_{resource_type}_{identifier}_{params_hash}
     * Example: xml_person_abc123_def456
     *
     * @param string $layer Layer prefix (xml, openapi, render, csl)
     * @param string $resourceType Resource type (person, research_output, etc.)
     * @param string $identifier UUID or unique identifier
     * @param array $params Additional parameters (will be hashed)
     * @return string Cache key
     */
    public function generateCacheKey(
        string $layer,
        string $resourceType,
        string $identifier,
        array $params = []
    ): string {
        // Normalize resource type
        $normalizedType = $this->normalizeResourceType($resourceType);

        // Generate params hash (empty params = empty hash part)
        $paramsHash = '';
        if (!empty($params)) {
            // Sort params for consistent hashing
            ksort($params);
            $paramsHash = '_' . substr(sha1(json_encode($params)), 0, 8);
        }

        // Build key: layer_type_uuid_paramshash
        $key = sprintf(
            '%s_%s_%s%s',
            $layer,
            $normalizedType,
            $this->sanitizeIdentifier($identifier),
            $paramsHash
        );

        return $key;
    }

    /**
     * Get cache tags for a resource
     *
     * Returns array of tags for selective clearing:
     * - Layer tag (pure_api, pure_rendering, etc.)
     * - Layer-specific tag (pure_api_xml, pure_api_openapi)
     * - Resource tag (pure_person_abc123)
     * - Type tag (pure_type_person)
     *
     * @param string $layer Layer prefix
     * @param string $resourceType Resource type
     * @param string $uuid Resource UUID
     * @return array Cache tags
     */
    public function getCacheTags(
        string $layer,
        string $resourceType,
        string $uuid
    ): array {
        $normalizedType = $this->normalizeResourceType($resourceType);
        $sanitizedUuid = $this->sanitizeIdentifier($uuid);

        $tags = [];

        // Add layer tag
        switch ($layer) {
            case self::LAYER_XML:
                $tags[] = self::TAG_API;
                $tags[] = self::TAG_API_XML;
                break;
            case self::LAYER_OPENAPI:
                $tags[] = self::TAG_API;
                $tags[] = self::TAG_API_OPENAPI;
                break;
            case self::LAYER_RENDER:
                $tags[] = self::TAG_RENDERING;
                break;
            case self::LAYER_CSL:
                $tags[] = self::TAG_CSL;
                break;
        }

        // Add resource-specific tag (e.g., pure_person_abc123)
        if (!empty($sanitizedUuid)) {
            $tags[] = sprintf(self::TAG_RESOURCE, $normalizedType, $sanitizedUuid);
        }

        // Add type tag (e.g., pure_type_person)
        $tags[] = sprintf(self::TAG_TYPE, $normalizedType);

        return $tags;
    }

    /**
     * Clear all caches for a specific resource (across all layers)
     *
     * Example: clearResource('person', 'abc-123-def')
     * Clears: XML API + OpenAPI + Rendering + CSL for this person
     *
     * @param string $resourceType Resource type
     * @param string $uuid Resource UUID
     */
    public function clearResource(string $resourceType, string $uuid): void
    {
        $normalizedType = $this->normalizeResourceType($resourceType);
        $sanitizedUuid = $this->sanitizeIdentifier($uuid);

        $tag = sprintf(self::TAG_RESOURCE, $normalizedType, $sanitizedUuid);

        $this->cache->flushByTag($tag);

        $this->logger->info('Cache cleared for resource', [
            'resource_type' => $normalizedType,
            'uuid' => $uuid,
            'tag' => $tag,
        ]);
    }

    /**
     * Clear all caches of a specific type (e.g., all persons)
     *
     * Example: clearResourceType('person')
     * Clears: All person caches across all layers
     *
     * @param string $resourceType Resource type
     */
    public function clearResourceType(string $resourceType): void
    {
        $normalizedType = $this->normalizeResourceType($resourceType);
        $tag = sprintf(self::TAG_TYPE, $normalizedType);

        $this->cache->flushByTag($tag);

        $this->logger->info('Cache cleared for resource type', [
            'resource_type' => $normalizedType,
            'tag' => $tag,
        ]);
    }

    /**
     * Clear all API caches (XML + OpenAPI)
     */
    public function clearApi(): void
    {
        $this->cache->flushByTag(self::TAG_API);

        $this->logger->info('All API caches cleared', [
            'tag' => self::TAG_API,
        ]);
    }

    /**
     * Clear specific API layer (xml or openapi)
     *
     * @param string $layer 'xml' or 'openapi'
     */
    public function clearApiLayer(string $layer): void
    {
        $tag = match ($layer) {
            self::LAYER_XML => self::TAG_API_XML,
            self::LAYER_OPENAPI => self::TAG_API_OPENAPI,
            default => throw new \InvalidArgumentException("Invalid API layer: {$layer}. Use 'xml' or 'openapi'.")
        };

        $this->cache->flushByTag($tag);

        $this->logger->info('API layer cache cleared', [
            'layer' => $layer,
            'tag' => $tag,
        ]);
    }

    /**
     * Clear all rendering caches
     */
    public function clearRendering(): void
    {
        $this->cache->flushByTag(self::TAG_RENDERING);

        $this->logger->info('All rendering caches cleared', [
            'tag' => self::TAG_RENDERING,
        ]);
    }

    /**
     * Clear all CSL caches
     */
    public function clearCsl(): void
    {
        $this->cache->flushByTag(self::TAG_CSL);

        $this->logger->info('All CSL caches cleared', [
            'tag' => self::TAG_CSL,
        ]);
    }

    /**
     * Clear all Pure caches (nuclear option)
     */
    public function clearAll(): void
    {
        $this->cache->flush();

        $this->logger->warning('All Pure caches cleared (complete flush)', [
            'warning' => 'Use selective clearing methods when possible',
        ]);
    }

    /**
     * Parse endpoint to extract resource information
     *
     * Handles various endpoint formats:
     * - /persons/abc-123-def → person, abc-123-def
     * - /research-outputs/xyz-789 → research_output, xyz-789
     * - /persons → person, '' (list query)
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters (may contain UUID)
     * @return array ['type' => 'person', 'uuid' => 'abc-123']
     */
    public function parseEndpoint(string $endpoint, array $params = []): array
    {
        // Remove leading slash
        $endpoint = ltrim($endpoint, '/');

        // Split by '/' to get [resource, uuid]
        $parts = explode('/', $endpoint);

        // Extract resource type (first part)
        $resourcePath = $parts[0] ?? '';

        // Map endpoint paths to resource types
        $resourceType = $this->mapEndpointToResourceType($resourcePath);

        // Extract UUID (second part, or from params)
        $uuid = '';
        if (isset($parts[1]) && !empty($parts[1])) {
            $uuid = $parts[1];
        } elseif (isset($params['uuid'])) {
            $uuid = $params['uuid'];
        } elseif (isset($params['id'])) {
            $uuid = $params['id'];
        }

        return [
            'type' => $resourceType,
            'uuid' => $uuid,
        ];
    }

    /**
     * Normalize resource type to internal format
     *
     * Handles various input formats:
     * - persons → person
     * - research-outputs → research_output
     * - organisational-units → organisation
     *
     * @param string $resourceType Resource type
     * @return string Normalized resource type
     */
    private function normalizeResourceType(string $resourceType): string
    {
        // Convert to lowercase
        $resourceType = strtolower($resourceType);

        // Remove plural 's' (persons → person)
        $resourceType = rtrim($resourceType, 's');

        // Replace hyphens with underscores (research-output → research_output)
        $resourceType = str_replace('-', '_', $resourceType);

        // Handle special cases
        $mappings = [
            'organisational_unit' => 'organisation',
            'organizational_unit' => 'organisation',
            'data_set' => 'dataset',
        ];

        return $mappings[$resourceType] ?? $resourceType;
    }

    /**
     * Map API endpoint path to resource type
     *
     * @param string $endpointPath Endpoint path (e.g., 'persons', 'research-outputs')
     * @return string Resource type
     */
    private function mapEndpointToResourceType(string $endpointPath): string
    {
        $mappings = [
            'persons' => self::RESOURCE_PERSON,
            'person' => self::RESOURCE_PERSON,
            'research-outputs' => self::RESOURCE_RESEARCH_OUTPUT,
            'research-output' => self::RESOURCE_RESEARCH_OUTPUT,
            'researchoutputs' => self::RESOURCE_RESEARCH_OUTPUT,
            'projects' => self::RESOURCE_PROJECT,
            'project' => self::RESOURCE_PROJECT,
            'organisational-units' => self::RESOURCE_ORGANISATION,
            'organisational-unit' => self::RESOURCE_ORGANISATION,
            'organizational-units' => self::RESOURCE_ORGANISATION,
            'organizations' => self::RESOURCE_ORGANISATION,
            'data-sets' => self::RESOURCE_DATASET,
            'data-set' => self::RESOURCE_DATASET,
            'datasets' => self::RESOURCE_DATASET,
            'equipments' => self::RESOURCE_EQUIPMENT,
            'equipment' => self::RESOURCE_EQUIPMENT,
        ];

        return $mappings[$endpointPath] ?? $endpointPath;
    }

    /**
     * Sanitize identifier for use in cache keys and tags
     *
     * Removes/replaces characters that are not allowed in cache tags
     *
     * @param string $identifier UUID or identifier
     * @return string Sanitized identifier
     */
    private function sanitizeIdentifier(string $identifier): string
    {
        // TYPO3 cache tags allow: a-z, A-Z, 0-9, _, -
        // UUIDs typically use hyphens, which are fine
        // Replace any other characters with underscores
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $identifier);
    }
}
