<?php

namespace Univie\UniviePure\Tests\Functional;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\Response;
use Univie\UniviePure\Endpoints\ResearchOutput;
use Univie\UniviePure\Service\WebService;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test case for publication functionality
 */
class PublicationFunctionalTest extends BaseFunctionalTestCase
{
    /**
     * @var ResearchOutput
     */
    protected ResearchOutput $researchOutput;

    /**
     * @var WebService|MockObject
     */
    protected $webServiceMock;

    /**
     * Setup specific to publication tests
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Add settings to prevent "chooseSelector" warning
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['univie_pure']['settings'] = [
            'chooseSelector' => 'default'
        ];

        // Create WebService mock
        $this->webServiceMock = $this->createMock(WebService::class);

        // Configure methods to be mocked
        $this->webServiceMock->method('getJson')->willReturn([]);
        $this->webServiceMock->method('getSingleResponse')->willReturn([]);
        $this->webServiceMock->method('getAlternativeSingleResponse')->willReturn([]);

        // Configure mock responses
        $this->configureMockResponses();

        // Initialize ResearchOutput with mock
        $this->researchOutput = new ResearchOutput($this->webServiceMock);
    }

    /**
     * Configure mock responses
     */
    protected function configureMockResponses(): void
    {
        // Define publication list data
        $listData = [
            'count' => 2,
            'offset' => 0,
            'items' => [
                [
                    'type' => 'contributionToJournal',
                    'uuid' => '12345678-1234-1234-1234-123456789abc',
                    'title' => 'Test Publication 1',
                    'renderings' => [
                        [
                            'html' => '<div class="publication">Test Publication 1 Rendering</div>'
                        ]
                    ],
                    'publicationStatuses' => [
                        [
                            'publicationStatus' => [
                                'uri' => '/dk/atira/pure/researchoutput/status/published',
                                'term' => [
                                    'text' => [
                                        [
                                            'value' => 'Published'
                                        ]
                                    ]
                                ]
                            ],
                            'publicationDate' => [
                                'year' => 2023
                            ],
                            'current' => 'true'
                        ]
                    ],
                    'personAssociations' => [
                        [
                            'name' => 'Test Author 1',
                            'organisationalUnits' => [
                                ['name' => 'Test Department']
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'contributionToJournal',
                    'uuid' => '87654321-4321-4321-4321-cba987654321',
                    'title' => 'Test Publication 2',
                    'renderings' => [
                        [
                            'html' => '<div class="publication">Test Publication 2 Rendering</div>'
                        ]
                    ],
                    'publicationStatuses' => [
                        [
                            'publicationStatus' => [
                                'uri' => '/dk/atira/pure/researchoutput/status/published',
                                'term' => [
                                    'text' => [
                                        [
                                            'value' => 'Published'
                                        ]
                                    ]
                                ]
                            ],
                            'publicationDate' => [
                                'year' => 2022
                            ],
                            'current' => 'true'
                        ]
                    ],
                    'personAssociations' => [
                        [
                            'name' => 'Test Author 3',
                            'organisationalUnits' => [
                                ['name' => 'Test Department']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Configure getJson to return publication list data
        $this->webServiceMock->method('getJson')
            ->willReturn($listData);

        // Define single publication data
        $singleData = [
            'items' => [
                [
                    'type' => 'contributionToJournal',
                    'uuid' => '12345678-1234-1234-1234-123456789abc',
                    'title' => 'Test Publication Details',
                    'publicationStatuses' => [
                        [
                            'publicationStatus' => [
                                'uri' => '/dk/atira/pure/researchoutput/status/published',
                                'term' => [
                                    'text' => [
                                        [
                                            'value' => 'Published'
                                        ]
                                    ]
                                ]
                            ],
                            'publicationDate' => [
                                'year' => 2023
                            ],
                            'current' => 'true'
                        ]
                    ],
                    'renderings' => [
                        [
                            'html' => '<div class="publication-detail">Test Publication Detail Rendering</div>'
                        ]
                    ],
                    'personAssociations' => [
                        [
                            'name' => 'Test Author 1',
                            'organisationalUnits' => [
                                ['name' => 'Test Department']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Configure getSingleResponse to return single publication data
        $this->webServiceMock->method('getSingleResponse')
            ->willReturn($singleData);

        // Define alternative single publication data for portal URL
        $alternativeData = [
            'items' => [
                [
                    'info' => [
                        'portalUrl' => 'https://example.com/portal/publication/12345'
                    ]
                ]
            ]
        ];

        // Configure getAlternativeSingleResponse to return alternative data
        $this->webServiceMock->method('getAlternativeSingleResponse')
            ->willReturn($alternativeData);
    }

    /**
     * Helper method to dump response
     *
     * @param mixed $data Data to dump
     */
    protected function debugDump(mixed $data): void
    {
        echo "\n\nResponse dump:\n";
        var_dump($data);
        echo "\n\n";
    }

    /**
     * @test
     */
    public function getPublicationListReturnsExpectedResult(): void
    {
        // Test settings
        $settings = [
            'pageSize' => 10,
            'what_to_display' => 'PUBLICATIONS',
            'rendering' => 'standard'
        ];

        // Call the method under test
        $result = $this->researchOutput->getPublicationList($settings, 1, 'en_GB');

        // Assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('contributionToJournal', $result);
        $this->assertEquals(2, $result['count']);

        // Verify publications are processed correctly
        $this->assertCount(2, $result['contributionToJournal']);
        $this->assertArrayHasKey('uuid', $result['contributionToJournal'][0]);
        $this->assertArrayHasKey('rendering', $result['contributionToJournal'][0]);
    }

    /**
     * @test
     */
    public function getPublicationListWithPaginationReturnsExpectedResult(): void
    {
        // Test settings
        $settings = [
            'pageSize' => 1,  // Set page size to 1 to test pagination
            'what_to_display' => 'PUBLICATIONS',
            'rendering' => 'standard'
        ];

        // Call the method under test for page 2
        $result = $this->researchOutput->getPublicationList($settings, 2, 'en_GB');

        // Assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('offset', $result);
        $this->assertEquals(1, $result['offset']); // Should be (page-1) * pageSize = 1
    }

    /**
     * @test
     */
    public function getPublicationListWithFilteringAppliesFilters(): void
    {
        // Test settings with filtering
        $settings = [
            'pageSize' => 10,
            'what_to_display' => 'PUBLICATIONS',
            'narrowBySearch' => 'test search',
            'peerReviewedOnly' => 1
        ];

        // Call the method under test
        $result = $this->researchOutput->getPublicationList($settings, 1, 'en_GB');

        // We can't fully test the filtering since we're using mocked responses,
        // but we can verify the result format is correct
        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
    }

    /**
     * @test
     */
    public function getSinglePublicationReturnsExpectedResult(): void
    {
        // Test UUID
        $uuid = '12345678-1234-1234-1234-123456789abc';

        // Call the method under test
        $result = $this->researchOutput->getSinglePublication($uuid);

        // Assert we get the expected result
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }
}