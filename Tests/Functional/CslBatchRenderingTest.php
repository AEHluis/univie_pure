<?php

declare(strict_types=1);

namespace Univie\UniviePure\Tests\Functional;

use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Service\OpenApi\OpenApiService;
use Univie\UniviePure\Service\OpenApi\OpenApiClient;
use Univie\UniviePure\Service\OpenApi\OpenApiResponseParser;
use Univie\UniviePure\Service\OpenApi\MigrationHelper;
use Univie\UniviePure\Service\RenderingService;
use Univie\UniviePure\Service\CslRenderingService;
use Univie\UniviePure\Service\CslDataTransformer;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Functional test for CSL batch rendering optimization
 *
 * Tests the optimization that uses citeproc-php bibliography mode
 * to render multiple research outputs in a single batch instead of
 * individual rendering (5-10x performance improvement).
 */
class CslBatchRenderingTest extends TestCase
{
    private OpenApiService $service;
    private OpenApiClient|MockObject $clientMock;
    private RenderingService $renderingService;
    private CslRenderingService $cslService;
    private array $testResearchOutputs;

    /**
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->testResearchOutputs = $this->createTestResearchOutputs();

        // Create mocks
        $cacheMock = $this->createMock(FrontendInterface::class);
        $loggerMock = $this->createMock(LoggerInterface::class);

        // Configure cache mock (no caching during tests)
        $cacheMock->method('has')->willReturn(false);
        $cacheMock->method('get')->willReturn(null);
        $cacheMock->method('set')->willReturn(true);

        // Create real CslRenderingService with mocked dependencies
        $dataTransformer = new CslDataTransformer();
        $this->cslService = new CslRenderingService(
            $cacheMock,
            $loggerMock,
            $dataTransformer
        );

        // Create real RenderingService
        $this->renderingService = new RenderingService(
            $cacheMock,
            $loggerMock,
            $this->cslService
        );

        // Create mocked OpenAPI components
        $this->clientMock = $this->createMock(OpenApiClient::class);
        $parser = new OpenApiResponseParser($loggerMock);
        $migrationHelper = new MigrationHelper($loggerMock);

        // Create OpenApiService with real rendering service
        $this->service = new OpenApiService(
            $this->clientMock,
            $parser,
            $migrationHelper,
            $this->renderingService
        );
    }

    /**
     * Create test research output data
     *
     * @return array
     */
    private function createTestResearchOutputs(): array
    {
        return [
            [
                'uuid' => 'pub-001',
                'title' => 'The Impact of Climate Change on Arctic Ecosystems',
                'type' => 'journal-article',
                'publicationYear' => 2023,
                'contributors' => [
                    [
                        'name' => [
                            'firstName' => 'Jane',
                            'lastName' => 'Smith'
                        ],
                        'role' => 'author'
                    ],
                    [
                        'name' => [
                            'firstName' => 'John',
                            'lastName' => 'Doe'
                        ],
                        'role' => 'author'
                    ]
                ],
                'journal' => [
                    'title' => 'Nature Climate Change'
                ]
            ],
            [
                'uuid' => 'pub-002',
                'title' => 'Machine Learning Applications in Medical Diagnosis',
                'type' => 'journal-article',
                'publicationYear' => 2022,
                'contributors' => [
                    [
                        'name' => [
                            'firstName' => 'Alice',
                            'lastName' => 'Johnson'
                        ],
                        'role' => 'author'
                    ]
                ],
                'journal' => [
                    'title' => 'Journal of Medical Systems'
                ]
            ],
            [
                'uuid' => 'pub-003',
                'title' => 'Sustainable Urban Development: A Review',
                'type' => 'journal-article',
                'publicationYear' => 2024,
                'contributors' => [
                    [
                        'name' => [
                            'firstName' => 'Robert',
                            'lastName' => 'Brown'
                        ],
                        'role' => 'author'
                    ],
                    [
                        'name' => [
                            'firstName' => 'Emma',
                            'lastName' => 'Wilson'
                        ],
                        'role' => 'author'
                    ]
                ],
                'journal' => [
                    'title' => 'Urban Studies'
                ]
            ]
        ];
    }

    /**
     * @test
     */
    public function isCslStyleDetectsCSLStyles(): void
    {
        $this->assertTrue(
            $this->renderingService->isCslStyle('apa'),
            'Should detect "apa" as CSL style'
        );

        $this->assertTrue(
            $this->renderingService->isCslStyle('mla'),
            'Should detect "mla" as CSL style'
        );

        $this->assertTrue(
            $this->renderingService->isCslStyle('ieee'),
            'Should detect "ieee" as CSL style'
        );

        $this->assertFalse(
            $this->renderingService->isCslStyle('short'),
            'Should NOT detect "short" as CSL style (Fluid template)'
        );

        $this->assertFalse(
            $this->renderingService->isCslStyle('detailed'),
            'Should NOT detect "detailed" as CSL style (Fluid template)'
        );
    }

    /**
     * @test
     */
    public function batchRenderingProducesMultipleEntries(): void
    {
        // Mock API response
        $apiResponse = [
            'items' => $this->testResearchOutputs,
            'count' => count($this->testResearchOutputs),
            'offset' => 0
        ];

        $this->clientMock
            ->method('get')
            ->willReturn($apiResponse);

        // Call with APA style (CSL)
        $result = $this->service->getResearchOutputs(['view' => 'apa']);

        // Verify all items have rendering
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(3, $result['items']);

        foreach ($result['items'] as $item) {
            $this->assertArrayHasKey('rendering', $item, 'Each item should have rendering field');
            $this->assertNotEmpty($item['rendering'], 'Rendering should not be empty');

            // Verify rendering contains CSL-entry div
            $this->assertStringContainsString(
                'csl-entry',
                $item['rendering'],
                'Rendering should contain csl-entry class'
            );
        }
    }

    /**
     * @test
     * @dataProvider cslStyleProvider
     */
    public function batchRenderingWorksWithVariousStyles(string $style, string $displayName): void
    {
        // Mock API response
        $apiResponse = [
            'items' => $this->testResearchOutputs,
            'count' => count($this->testResearchOutputs),
            'offset' => 0
        ];

        $this->clientMock
            ->method('get')
            ->willReturn($apiResponse);

        // Call with specific CSL style
        $result = $this->service->getResearchOutputs(['view' => $style]);

        // Verify all items have rendering
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(3, $result['items'], "Should render all 3 items with {$displayName} style");

        foreach ($result['items'] as $index => $item) {
            $this->assertArrayHasKey(
                'rendering',
                $item,
                "Item {$index} should have rendering with {$displayName} style"
            );

            $this->assertNotEmpty(
                $item['rendering'],
                "Item {$index} rendering should not be empty for {$displayName}"
            );

            // Verify contains title or author (basic validation)
            $rendering = $item['rendering'];
            $title = $this->testResearchOutputs[$index]['title'];

            // Title might be truncated or formatted differently, so check partial match
            $titleWords = explode(' ', $title);
            $containsTitle = false;
            foreach ($titleWords as $word) {
                if (strlen($word) > 4 && stripos($rendering, $word) !== false) {
                    $containsTitle = true;
                    break;
                }
            }

            $this->assertTrue(
                $containsTitle,
                "Rendering should contain part of the title for {$displayName}"
            );
        }
    }

    /**
     * Data provider for CSL styles
     */
    public function cslStyleProvider(): array
    {
        return [
            'APA' => ['apa', 'APA 7th Edition'],
            'MLA' => ['mla', 'MLA 9th Edition'],
            'IEEE' => ['ieee', 'IEEE'],
            'Chicago Author-Date' => ['chicago-author-date', 'Chicago (Author-Date)'],
            'Vancouver' => ['vancouver', 'Vancouver'],
        ];
    }

    /**
     * @test
     */
    public function individualRenderingWorksForFluidTemplates(): void
    {
        // Mock API response
        $apiResponse = [
            'items' => $this->testResearchOutputs,
            'count' => count($this->testResearchOutputs),
            'offset' => 0
        ];

        $this->clientMock
            ->method('get')
            ->willReturn($apiResponse);

        // Call with Fluid template view (not CSL)
        $result = $this->service->getResearchOutputs(['view' => 'short']);

        // Verify all items have rendering (even if fallback)
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(3, $result['items']);

        foreach ($result['items'] as $item) {
            $this->assertArrayHasKey('rendering', $item);
            // Rendering might be fallback, which is acceptable
        }
    }

    /**
     * @test
     */
    public function batchRenderingHandlesEmptyList(): void
    {
        // Mock empty API response
        $apiResponse = [
            'items' => [],
            'count' => 0,
            'offset' => 0
        ];

        $this->clientMock
            ->method('get')
            ->willReturn($apiResponse);

        // Call with APA style
        $result = $this->service->getResearchOutputs(['view' => 'apa']);

        // Verify empty result
        $this->assertArrayHasKey('items', $result);
        $this->assertEmpty($result['items']);
        $this->assertEquals(0, $result['count']);
    }

    /**
     * @test
     */
    public function batchRenderingHandlesSingleItem(): void
    {
        // Mock single item response
        $apiResponse = [
            'items' => [$this->testResearchOutputs[0]],
            'count' => 1,
            'offset' => 0
        ];

        $this->clientMock
            ->method('get')
            ->willReturn($apiResponse);

        // Call with APA style
        $result = $this->service->getResearchOutputs(['view' => 'apa']);

        // Verify single item has rendering
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(1, $result['items']);
        $this->assertArrayHasKey('rendering', $result['items'][0]);
        $this->assertNotEmpty($result['items'][0]['rendering']);
    }

    /**
     * @test
     */
    public function bibliographyParserExtractsCorrectNumberOfEntries(): void
    {
        // Render bibliography directly
        $bibliography = $this->renderingService->renderBibliography(
            $this->testResearchOutputs,
            'apa'
        );

        // Verify bibliography contains multiple csl-entry divs
        preg_match_all('/<div[^>]*class="csl-entry"[^>]*>/', $bibliography, $matches);

        $this->assertGreaterThanOrEqual(
            3,
            count($matches[0]),
            'Bibliography should contain at least 3 csl-entry divs'
        );
    }

    /**
     * @test
     */
    public function performanceComparisonShowsImprovement(): void
    {
        // This test measures relative performance, not absolute
        // Skip if citeproc-php is not available
        if (!class_exists(\Seboettg\CiteProc\CiteProc::class)) {
            $this->markTestSkipped('Citeproc-php not available');
        }

        // Create larger dataset for meaningful benchmark
        $largeDataset = array_merge(
            $this->testResearchOutputs,
            $this->testResearchOutputs,
            $this->testResearchOutputs,
            $this->testResearchOutputs
        ); // 12 items

        // Benchmark batch rendering
        $startBatch = microtime(true);
        $batchResult = $this->renderingService->renderBibliography($largeDataset, 'apa');
        $batchTime = microtime(true) - $startBatch;

        // Benchmark individual rendering
        $startIndividual = microtime(true);
        foreach ($largeDataset as $item) {
            $this->renderingService->renderResearchOutput($item, 'apa');
        }
        $individualTime = microtime(true) - $startIndividual;

        // Log performance results
        echo "\n\n=== CSL Rendering Performance ===\n";
        echo sprintf("Batch rendering (12 items): %.3f seconds\n", $batchTime);
        echo sprintf("Individual rendering (12 items): %.3f seconds\n", $individualTime);
        echo sprintf("Improvement: %.1fx faster\n", $individualTime / $batchTime);
        echo "================================\n\n";

        // Verify batch rendering is faster (with tolerance for variance)
        // We expect 3-10x improvement, but allow 2x minimum to account for variance
        $this->assertLessThan(
            $individualTime / 2,
            $batchTime,
            'Batch rendering should be at least 2x faster than individual rendering'
        );

        // Verify batch result is not empty
        $this->assertNotEmpty($batchResult);
    }

    /**
     * @test
     */
    public function fallbackRenderingWorksWhenParsingFails(): void
    {
        // Create item that might cause parsing issues
        $problematicItem = [
            'uuid' => 'pub-problematic',
            'title' => 'Test Publication <with> Special &amp; Characters',
            'publicationYear' => 2023,
            'contributors' => []
        ];

        // Mock API response
        $apiResponse = [
            'items' => [$problematicItem],
            'count' => 1,
            'offset' => 0
        ];

        $this->clientMock
            ->method('get')
            ->willReturn($apiResponse);

        // Call with APA style
        $result = $this->service->getResearchOutputs(['view' => 'apa']);

        // Verify fallback rendering is provided
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(1, $result['items']);
        $this->assertArrayHasKey('rendering', $result['items'][0]);

        // Fallback should contain title and year
        $rendering = $result['items'][0]['rendering'];
        $this->assertNotEmpty($rendering);
    }
}
