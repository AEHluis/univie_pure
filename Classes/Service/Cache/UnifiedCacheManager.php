<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\Cache;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Unified Cache Manager for Pure API responses
 *
 * Provides centralized cache management with tag-based cache invalidation.
 * Supports different cache layers: API responses, rendered content, and CSL citations.
 */
class UnifiedCacheManager
{
    /**
     * Cache tag prefixes for different content types
     */
    private const TAG_PREFIX_API = 'pure_api';
    private const TAG_PREFIX_RENDER = 'pure_render';
    private const TAG_PREFIX_CSL = 'pure_csl';

    /**
     * Cache tag suffixes for entity types
     */
    private const TAG_RESEARCH_OUTPUT = 'research_output';
    private const TAG_PERSON = 'person';
    private const TAG_PROJECT = 'project';
    private const TAG_EQUIPMENT = 'equipment';
    private const TAG_DATASET = 'dataset';
    private const TAG_ORGANISATION = 'organisation';

    /**
     * Default cache lifetime in seconds (4 hours)
     */
    private const DEFAULT_LIFETIME = 14400;

    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get cached content by key
     *
     * @param string $cacheKey The cache identifier
     * @return mixed|null The cached data or null if not found
     */
    public function get(string $cacheKey): mixed
    {
        $identifier = $this->sanitizeIdentifier($cacheKey);

        if ($this->cache->has($identifier)) {
            $this->logger->debug('Cache hit', ['key' => $identifier]);
            $data = $this->cache->get($identifier);
            return $data !== false ? $data : null;
        }

        $this->logger->debug('Cache miss', ['key' => $identifier]);
        return null;
    }

    /**
     * Store content in cache with tags
     *
     * @param string $cacheKey The cache identifier
     * @param mixed $data The data to cache
     * @param array $tags Cache tags for invalidation
     * @param int|null $lifetime Cache lifetime in seconds (null = default)
     */
    public function set(string $cacheKey, mixed $data, array $tags = [], ?int $lifetime = null): void
    {
        $identifier = $this->sanitizeIdentifier($cacheKey);
        $sanitizedTags = array_map([$this, 'sanitizeTag'], $tags);
        $ttl = $lifetime ?? self::DEFAULT_LIFETIME;

        $this->cache->set($identifier, $data, $sanitizedTags, $ttl);

        $this->logger->debug('Cache set', [
            'key' => $identifier,
            'tags' => $sanitizedTags,
            'lifetime' => $ttl,
        ]);
    }

    /**
     * Remove specific cache entry
     *
     * @param string $cacheKey The cache identifier to remove
     */
    public function remove(string $cacheKey): void
    {
        $identifier = $this->sanitizeIdentifier($cacheKey);
        $this->cache->remove($identifier);

        $this->logger->debug('Cache entry removed', ['key' => $identifier]);
    }

    /**
     * Flush cache entries by tag
     *
     * @param string $tag The tag to flush
     */
    public function flushByTag(string $tag): void
    {
        $sanitizedTag = $this->sanitizeTag($tag);
        $this->cache->flushByTag($sanitizedTag);

        $this->logger->info('Cache flushed by tag', ['tag' => $sanitizedTag]);
    }

    /**
     * Flush cache entries by multiple tags
     *
     * @param array $tags Array of tags to flush
     */
    public function flushByTags(array $tags): void
    {
        $sanitizedTags = array_map([$this, 'sanitizeTag'], $tags);
        $this->cache->flushByTags($sanitizedTags);

        $this->logger->info('Cache flushed by tags', ['tags' => $sanitizedTags]);
    }

    /**
     * Flush all Pure-related cache entries
     */
    public function flushAll(): void
    {
        $this->cache->flush();
        $this->logger->info('All cache entries flushed');
    }

    // =========================================================================
    // API Cache Methods
    // =========================================================================

    /**
     * Generate cache key for API response
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param string $format Response format (json/xml)
     * @return string Cache key
     */
    public function generateApiCacheKey(string $endpoint, array $params = [], string $format = 'json'): string
    {
        $keyData = [
            'endpoint' => $endpoint,
            'params' => $params,
            'format' => $format,
        ];

        return 'api_' . md5(json_encode($keyData));
    }

    /**
     * Get API tags for an entity type
     *
     * @param string $entityType Entity type (research_output, person, etc.)
     * @param string|null $uuid Optional specific UUID
     * @return array Array of cache tags
     */
    public function getApiTags(string $entityType, ?string $uuid = null): array
    {
        $tags = [
            self::TAG_PREFIX_API,
            self::TAG_PREFIX_API . '_' . $entityType,
        ];

        if ($uuid !== null) {
            $tags[] = self::TAG_PREFIX_API . '_uuid_' . $uuid;
        }

        return $tags;
    }

    /**
     * Cache API response
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param mixed $data Response data
     * @param string $entityType Entity type for tagging
     * @param string|null $uuid Optional specific UUID
     */
    public function cacheApiResponse(
        string $endpoint,
        array $params,
        mixed $data,
        string $entityType = 'generic',
        ?string $uuid = null
    ): void {
        $cacheKey = $this->generateApiCacheKey($endpoint, $params);
        $tags = $this->getApiTags($entityType, $uuid);

        $this->set($cacheKey, $data, $tags);
    }

    /**
     * Get cached API response
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return mixed|null Cached data or null
     */
    public function getCachedApiResponse(string $endpoint, array $params = []): mixed
    {
        $cacheKey = $this->generateApiCacheKey($endpoint, $params);
        return $this->get($cacheKey);
    }

    // =========================================================================
    // Render Cache Methods
    // =========================================================================

    /**
     * Generate cache key for rendered content
     *
     * @param string $entityType Entity type
     * @param string $uuid Entity UUID
     * @param string $rendering Rendering type (short, detailed, etc.)
     * @param string $locale Locale code
     * @return string Cache key
     */
    public function generateRenderCacheKey(
        string $entityType,
        string $uuid,
        string $rendering = 'short',
        string $locale = 'en'
    ): string {
        return 'render_' . md5(implode('_', [$entityType, $uuid, $rendering, $locale]));
    }

    /**
     * Get render tags for an entity
     *
     * @param string $entityType Entity type
     * @param string $uuid Entity UUID
     * @return array Array of cache tags
     */
    public function getRenderTags(string $entityType, string $uuid): array
    {
        return [
            self::TAG_PREFIX_RENDER,
            self::TAG_PREFIX_RENDER . '_' . $entityType,
            self::TAG_PREFIX_RENDER . '_uuid_' . $uuid,
        ];
    }

    /**
     * Cache rendered content
     *
     * @param string $entityType Entity type
     * @param string $uuid Entity UUID
     * @param string $html Rendered HTML
     * @param string $rendering Rendering type
     * @param string $locale Locale code
     */
    public function cacheRenderedContent(
        string $entityType,
        string $uuid,
        string $html,
        string $rendering = 'short',
        string $locale = 'en'
    ): void {
        $cacheKey = $this->generateRenderCacheKey($entityType, $uuid, $rendering, $locale);
        $tags = $this->getRenderTags($entityType, $uuid);

        $this->set($cacheKey, $html, $tags);
    }

    /**
     * Get cached rendered content
     *
     * @param string $entityType Entity type
     * @param string $uuid Entity UUID
     * @param string $rendering Rendering type
     * @param string $locale Locale code
     * @return string|null Cached HTML or null
     */
    public function getCachedRenderedContent(
        string $entityType,
        string $uuid,
        string $rendering = 'short',
        string $locale = 'en'
    ): ?string {
        $cacheKey = $this->generateRenderCacheKey($entityType, $uuid, $rendering, $locale);
        $result = $this->get($cacheKey);
        return is_string($result) ? $result : null;
    }

    // =========================================================================
    // CSL Cache Methods
    // =========================================================================

    /**
     * Generate cache key for CSL citation
     *
     * @param string $uuid Publication UUID
     * @param string $style CSL style name
     * @param string $locale Locale code
     * @return string Cache key
     */
    public function generateCslCacheKey(string $uuid, string $style, string $locale = 'en'): string
    {
        return 'csl_' . md5(implode('_', [$uuid, $style, $locale]));
    }

    /**
     * Get CSL tags for a publication
     *
     * @param string $uuid Publication UUID
     * @param string $style CSL style name
     * @return array Array of cache tags
     */
    public function getCslTags(string $uuid, string $style): array
    {
        return [
            self::TAG_PREFIX_CSL,
            self::TAG_PREFIX_CSL . '_style_' . $style,
            self::TAG_PREFIX_CSL . '_uuid_' . $uuid,
        ];
    }

    /**
     * Cache CSL citation
     *
     * @param string $uuid Publication UUID
     * @param string $style CSL style name
     * @param string $citation Rendered citation HTML
     * @param string $locale Locale code
     */
    public function cacheCslCitation(
        string $uuid,
        string $style,
        string $citation,
        string $locale = 'en'
    ): void {
        $cacheKey = $this->generateCslCacheKey($uuid, $style, $locale);
        $tags = $this->getCslTags($uuid, $style);

        $this->set($cacheKey, $citation, $tags);
    }

    /**
     * Get cached CSL citation
     *
     * @param string $uuid Publication UUID
     * @param string $style CSL style name
     * @param string $locale Locale code
     * @return string|null Cached citation or null
     */
    public function getCachedCslCitation(string $uuid, string $style, string $locale = 'en'): ?string
    {
        $cacheKey = $this->generateCslCacheKey($uuid, $style, $locale);
        $result = $this->get($cacheKey);
        return is_string($result) ? $result : null;
    }

    // =========================================================================
    // Selective Cache Invalidation
    // =========================================================================

    /**
     * Invalidate all cache for a specific entity
     *
     * @param string $uuid Entity UUID
     */
    public function invalidateEntity(string $uuid): void
    {
        $this->flushByTags([
            self::TAG_PREFIX_API . '_uuid_' . $uuid,
            self::TAG_PREFIX_RENDER . '_uuid_' . $uuid,
            self::TAG_PREFIX_CSL . '_uuid_' . $uuid,
        ]);

        $this->logger->info('Entity cache invalidated', ['uuid' => $uuid]);
    }

    /**
     * Invalidate all cache for a specific entity type
     *
     * @param string $entityType Entity type (research_output, person, etc.)
     */
    public function invalidateEntityType(string $entityType): void
    {
        $this->flushByTags([
            self::TAG_PREFIX_API . '_' . $entityType,
            self::TAG_PREFIX_RENDER . '_' . $entityType,
        ]);

        $this->logger->info('Entity type cache invalidated', ['type' => $entityType]);
    }

    /**
     * Invalidate all API cache
     */
    public function invalidateApiCache(): void
    {
        $this->flushByTag(self::TAG_PREFIX_API);
        $this->logger->info('API cache invalidated');
    }

    /**
     * Invalidate all render cache
     */
    public function invalidateRenderCache(): void
    {
        $this->flushByTag(self::TAG_PREFIX_RENDER);
        $this->logger->info('Render cache invalidated');
    }

    /**
     * Invalidate all CSL cache
     */
    public function invalidateCslCache(): void
    {
        $this->flushByTag(self::TAG_PREFIX_CSL);
        $this->logger->info('CSL cache invalidated');
    }

    /**
     * Invalidate CSL cache for a specific style
     *
     * @param string $style CSL style name
     */
    public function invalidateCslStyle(string $style): void
    {
        $this->flushByTag(self::TAG_PREFIX_CSL . '_style_' . $style);
        $this->logger->info('CSL style cache invalidated', ['style' => $style]);
    }

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Check if cache entry exists
     *
     * @param string $cacheKey Cache identifier
     * @return bool True if entry exists
     */
    public function has(string $cacheKey): bool
    {
        $identifier = $this->sanitizeIdentifier($cacheKey);
        return $this->cache->has($identifier);
    }

    /**
     * Get cache statistics (if available)
     *
     * @return array Statistics array
     */
    public function getStatistics(): array
    {
        return [
            'backend' => get_class($this->cache->getBackend()),
            'identifier' => $this->cache->getIdentifier(),
        ];
    }

    /**
     * Sanitize cache identifier to meet TYPO3 requirements
     *
     * @param string $identifier Raw identifier
     * @return string Sanitized identifier
     */
    private function sanitizeIdentifier(string $identifier): string
    {
        // TYPO3 cache identifiers must match: ^[a-zA-Z0-9_%\\-&]{1,250}$
        $sanitized = preg_replace('/[^a-zA-Z0-9_%\-&]/', '_', $identifier);
        return substr($sanitized, 0, 250);
    }

    /**
     * Sanitize cache tag to meet TYPO3 requirements
     *
     * @param string $tag Raw tag
     * @return string Sanitized tag
     */
    private function sanitizeTag(string $tag): string
    {
        // Tags should be alphanumeric with underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $tag);
        return substr($sanitized, 0, 250);
    }

    /**
     * Get entity type constant for tagging
     *
     * @param string $type Entity type string
     * @return string Normalized entity type tag
     */
    public static function normalizeEntityType(string $type): string
    {
        return match (strtolower($type)) {
            'research-output', 'research-outputs', 'researchoutput', 'publication', 'publications' => self::TAG_RESEARCH_OUTPUT,
            'person', 'persons' => self::TAG_PERSON,
            'project', 'projects' => self::TAG_PROJECT,
            'equipment', 'equipments' => self::TAG_EQUIPMENT,
            'data-set', 'data-sets', 'dataset', 'datasets' => self::TAG_DATASET,
            'organisation', 'organisations', 'organisational-unit', 'organisational-units' => self::TAG_ORGANISATION,
            default => $type,
        };
    }
}
