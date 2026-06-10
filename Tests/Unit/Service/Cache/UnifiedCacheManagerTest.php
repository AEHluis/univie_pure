<?php

declare(strict_types=1);

namespace Univie\UniviePure\Tests\Unit\Service\Cache;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use Univie\UniviePure\Service\Cache\UnifiedCacheManager;

/**
 * Test case for UnifiedCacheManager
 */
class UnifiedCacheManagerTest extends TestCase
{
    private FrontendInterface|MockObject $cacheMock;
    private LoggerInterface|MockObject $loggerMock;
    private UnifiedCacheManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheMock = $this->createMock(FrontendInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->manager = new UnifiedCacheManager(
            $this->cacheMock,
            $this->loggerMock
        );
    }

    #[Test]
    public function getReturnsCachedData(): void
    {
        $cacheKey = 'test_key';
        $cachedData = ['test' => 'data'];

        $this->cacheMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($cachedData);

        $result = $this->manager->get($cacheKey);

        $this->assertEquals($cachedData, $result);
    }

    #[Test]
    public function getReturnsNullOnCacheMiss(): void
    {
        $cacheKey = 'nonexistent_key';

        $this->cacheMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(false);

        $result = $this->manager->get($cacheKey);

        $this->assertNull($result);
    }

    #[Test]
    public function setStoresCacheData(): void
    {
        $cacheKey = 'test_key';
        $data = ['test' => 'data'];
        $tags = ['tag1', 'tag2'];

        $this->cacheMock
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->stringContains('test_key'),
                $data,
                $this->isType('array'),
                14400 // default lifetime
            );

        $this->manager->set($cacheKey, $data, $tags);
    }

    #[Test]
    public function setUsesCustomLifetime(): void
    {
        $cacheKey = 'test_key';
        $data = ['test' => 'data'];
        $customLifetime = 3600;

        $this->cacheMock
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->anything(),
                $data,
                $this->anything(),
                $customLifetime
            );

        $this->manager->set($cacheKey, $data, [], $customLifetime);
    }

    #[Test]
    public function removeDeletesCacheEntry(): void
    {
        $cacheKey = 'test_key';

        $this->cacheMock
            ->expects($this->once())
            ->method('remove')
            ->with($this->stringContains('test_key'));

        $this->manager->remove($cacheKey);
    }

    #[Test]
    public function flushByTagFlushesCorrectTag(): void
    {
        $tag = 'test_tag';

        $this->cacheMock
            ->expects($this->once())
            ->method('flushByTag')
            ->with('test_tag');

        $this->manager->flushByTag($tag);
    }

    #[Test]
    public function flushByTagsFlushesMultipleTags(): void
    {
        $tags = ['tag1', 'tag2', 'tag3'];

        $this->cacheMock
            ->expects($this->once())
            ->method('flushByTags')
            ->with($tags);

        $this->manager->flushByTags($tags);
    }

    #[Test]
    public function flushAllClearsEntireCache(): void
    {
        $this->cacheMock
            ->expects($this->once())
            ->method('flush');

        $this->manager->flushAll();
    }

    #[Test]
    public function generateApiCacheKeyCreatesConsistentKeys(): void
    {
        $endpoint = 'research-outputs';
        $params = ['limit' => 10, 'offset' => 0];

        $key1 = $this->manager->generateApiCacheKey($endpoint, $params);
        $key2 = $this->manager->generateApiCacheKey($endpoint, $params);

        $this->assertEquals($key1, $key2);
        $this->assertStringStartsWith('api_', $key1);
    }

    #[Test]
    public function generateApiCacheKeyDiffersForDifferentParams(): void
    {
        $endpoint = 'research-outputs';

        $key1 = $this->manager->generateApiCacheKey($endpoint, ['limit' => 10]);
        $key2 = $this->manager->generateApiCacheKey($endpoint, ['limit' => 20]);

        $this->assertNotEquals($key1, $key2);
    }

    #[Test]
    public function getApiTagsReturnsCorrectTags(): void
    {
        $entityType = 'research_output';
        $uuid = 'test-uuid-123';

        $tags = $this->manager->getApiTags($entityType, $uuid);

        $this->assertContains('pure_api', $tags);
        $this->assertContains('pure_api_research_output', $tags);
        $this->assertContains('pure_api_uuid_test-uuid-123', $tags);
    }

    #[Test]
    public function getApiTagsWithoutUuidReturnsCorrectTags(): void
    {
        $entityType = 'person';

        $tags = $this->manager->getApiTags($entityType);

        $this->assertContains('pure_api', $tags);
        $this->assertContains('pure_api_person', $tags);
        $this->assertCount(2, $tags);
    }

    #[Test]
    public function generateRenderCacheKeyCreatesConsistentKeys(): void
    {
        $entityType = 'research_output';
        $uuid = 'test-uuid';
        $rendering = 'short';
        $locale = 'en';

        $key1 = $this->manager->generateRenderCacheKey($entityType, $uuid, $rendering, $locale);
        $key2 = $this->manager->generateRenderCacheKey($entityType, $uuid, $rendering, $locale);

        $this->assertEquals($key1, $key2);
        $this->assertStringStartsWith('render_', $key1);
    }

    #[Test]
    public function getRenderTagsReturnsCorrectTags(): void
    {
        $entityType = 'project';
        $uuid = 'project-uuid';

        $tags = $this->manager->getRenderTags($entityType, $uuid);

        $this->assertContains('pure_render', $tags);
        $this->assertContains('pure_render_project', $tags);
        $this->assertContains('pure_render_uuid_project-uuid', $tags);
    }

    #[Test]
    public function generateCslCacheKeyCreatesConsistentKeys(): void
    {
        $uuid = 'pub-uuid';
        $style = 'apa';
        $locale = 'en';

        $key1 = $this->manager->generateCslCacheKey($uuid, $style, $locale);
        $key2 = $this->manager->generateCslCacheKey($uuid, $style, $locale);

        $this->assertEquals($key1, $key2);
        $this->assertStringStartsWith('csl_', $key1);
    }

    #[Test]
    public function getCslTagsReturnsCorrectTags(): void
    {
        $uuid = 'pub-uuid';
        $style = 'ieee';

        $tags = $this->manager->getCslTags($uuid, $style);

        $this->assertContains('pure_csl', $tags);
        $this->assertContains('pure_csl_style_ieee', $tags);
        $this->assertContains('pure_csl_uuid_pub-uuid', $tags);
    }

    #[Test]
    public function cacheApiResponseStoresWithCorrectTags(): void
    {
        $endpoint = 'persons';
        $params = ['limit' => 10];
        $data = ['items' => []];
        $entityType = 'person';
        $uuid = 'person-uuid';

        $this->cacheMock
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->stringStartsWith('api_'),
                $data,
                $this->callback(function ($tags) {
                    // Tags are sanitized (dashes converted to underscores)
                    return in_array('pure_api', $tags)
                        && in_array('pure_api_person', $tags)
                        && in_array('pure_api_uuid_person_uuid', $tags);
                }),
                $this->anything()
            );

        $this->manager->cacheApiResponse($endpoint, $params, $data, $entityType, $uuid);
    }

    #[Test]
    public function getCachedApiResponseReturnsCachedData(): void
    {
        $endpoint = 'projects';
        $params = ['q' => 'test'];
        $cachedData = ['items' => [['uuid' => '1']]];

        $this->cacheMock
            ->method('has')
            ->willReturn(true);

        $this->cacheMock
            ->method('get')
            ->willReturn($cachedData);

        $result = $this->manager->getCachedApiResponse($endpoint, $params);

        $this->assertEquals($cachedData, $result);
    }

    #[Test]
    public function cacheRenderedContentStoresHtml(): void
    {
        $entityType = 'research_output';
        $uuid = 'pub-uuid';
        $html = '<div>Test publication</div>';
        $rendering = 'detailed';
        $locale = 'de';

        $this->cacheMock
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->stringStartsWith('render_'),
                $html,
                $this->isType('array'),
                $this->anything()
            );

        $this->manager->cacheRenderedContent($entityType, $uuid, $html, $rendering, $locale);
    }

    #[Test]
    public function getCachedRenderedContentReturnsString(): void
    {
        $entityType = 'person';
        $uuid = 'person-uuid';
        $cachedHtml = '<div>Person name</div>';

        $this->cacheMock
            ->method('has')
            ->willReturn(true);

        $this->cacheMock
            ->method('get')
            ->willReturn($cachedHtml);

        $result = $this->manager->getCachedRenderedContent($entityType, $uuid);

        $this->assertEquals($cachedHtml, $result);
    }

    #[Test]
    public function getCachedRenderedContentReturnsNullForNonString(): void
    {
        $this->cacheMock
            ->method('has')
            ->willReturn(true);

        $this->cacheMock
            ->method('get')
            ->willReturn(['not' => 'a string']);

        $result = $this->manager->getCachedRenderedContent('type', 'uuid');

        $this->assertNull($result);
    }

    #[Test]
    public function cacheCslCitationStoresCitation(): void
    {
        $uuid = 'pub-uuid';
        $style = 'chicago-author-date';
        $citation = '<div class="csl-entry">Author (2024)</div>';
        $locale = 'en';

        $this->cacheMock
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->stringStartsWith('csl_'),
                $citation,
                $this->callback(function ($tags) {
                    return in_array('pure_csl', $tags)
                        && in_array('pure_csl_style_chicago_author_date', $tags);
                }),
                $this->anything()
            );

        $this->manager->cacheCslCitation($uuid, $style, $citation, $locale);
    }

    #[Test]
    public function getCachedCslCitationReturnsCachedCitation(): void
    {
        $uuid = 'pub-uuid';
        $style = 'vancouver';
        $cachedCitation = '<div class="csl-entry">1. Author, 2024</div>';

        $this->cacheMock
            ->method('has')
            ->willReturn(true);

        $this->cacheMock
            ->method('get')
            ->willReturn($cachedCitation);

        $result = $this->manager->getCachedCslCitation($uuid, $style);

        $this->assertEquals($cachedCitation, $result);
    }

    #[Test]
    public function invalidateEntityFlushesAllEntityTags(): void
    {
        $uuid = 'entity-uuid';

        $this->cacheMock
            ->expects($this->once())
            ->method('flushByTags')
            ->with($this->callback(function ($tags) {
                // Tags are sanitized (dashes converted to underscores)
                return in_array('pure_api_uuid_entity_uuid', $tags)
                    && in_array('pure_render_uuid_entity_uuid', $tags)
                    && in_array('pure_csl_uuid_entity_uuid', $tags);
            }));

        $this->manager->invalidateEntity($uuid);

        $this->assertTrue(true); // Prevent risky test warning
    }

    #[Test]
    public function invalidateEntityTypeFlushesTypeTags(): void
    {
        $entityType = 'project';

        $this->cacheMock
            ->expects($this->once())
            ->method('flushByTags')
            ->with($this->callback(function ($tags) {
                return in_array('pure_api_project', $tags)
                    && in_array('pure_render_project', $tags);
            }));

        $this->manager->invalidateEntityType($entityType);
    }

    #[Test]
    public function invalidateApiCacheFlushesApiTag(): void
    {
        $this->cacheMock
            ->expects($this->once())
            ->method('flushByTag')
            ->with('pure_api');

        $this->manager->invalidateApiCache();
    }

    #[Test]
    public function invalidateRenderCacheFlushesRenderTag(): void
    {
        $this->cacheMock
            ->expects($this->once())
            ->method('flushByTag')
            ->with('pure_render');

        $this->manager->invalidateRenderCache();
    }

    #[Test]
    public function invalidateCslCacheFlushesCslTag(): void
    {
        $this->cacheMock
            ->expects($this->once())
            ->method('flushByTag')
            ->with('pure_csl');

        $this->manager->invalidateCslCache();
    }

    #[Test]
    public function invalidateCslStyleFlushesStyleTag(): void
    {
        $style = 'harvard-cite-them-right';

        $this->cacheMock
            ->expects($this->once())
            ->method('flushByTag')
            ->with('pure_csl_style_harvard_cite_them_right');

        $this->manager->invalidateCslStyle($style);
    }

    #[Test]
    public function hasChecksForCacheEntry(): void
    {
        $cacheKey = 'test_key';

        $this->cacheMock
            ->expects($this->once())
            ->method('has')
            ->with($this->stringContains('test_key'))
            ->willReturn(true);

        $result = $this->manager->has($cacheKey);

        $this->assertTrue($result);
    }

    #[Test]
    public function normalizeEntityTypeReturnsCorrectValues(): void
    {
        $this->assertEquals('research_output', UnifiedCacheManager::normalizeEntityType('research-outputs'));
        $this->assertEquals('research_output', UnifiedCacheManager::normalizeEntityType('publication'));
        $this->assertEquals('person', UnifiedCacheManager::normalizeEntityType('persons'));
        $this->assertEquals('project', UnifiedCacheManager::normalizeEntityType('projects'));
        $this->assertEquals('equipment', UnifiedCacheManager::normalizeEntityType('equipments'));
        $this->assertEquals('dataset', UnifiedCacheManager::normalizeEntityType('data-sets'));
        $this->assertEquals('organisation', UnifiedCacheManager::normalizeEntityType('organisational-units'));
        $this->assertEquals('unknown', UnifiedCacheManager::normalizeEntityType('unknown'));
    }

    #[Test]
    public function getStatisticsReturnsBackendInfo(): void
    {
        $backendMock = $this->createMock(\TYPO3\CMS\Core\Cache\Backend\BackendInterface::class);

        $this->cacheMock
            ->method('getBackend')
            ->willReturn($backendMock);

        $this->cacheMock
            ->method('getIdentifier')
            ->willReturn('univie_pure');

        $stats = $this->manager->getStatistics();

        $this->assertArrayHasKey('backend', $stats);
        $this->assertArrayHasKey('identifier', $stats);
        $this->assertEquals('univie_pure', $stats['identifier']);
    }
}
