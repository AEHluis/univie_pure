<?php

namespace Univie\UniviePure\Tests\Functional;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\Response;
use Univie\UniviePure\Endpoints\Equipments;
use Univie\UniviePure\Service\WebService;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test case for equipments functionality
 */
class EquipmentsFunctionalTest extends BaseFunctionalTestCase
{
    /**
     * @var Equipments
     */
    protected Equipments $equipments;

    /**
     * @var WebService|MockObject
     */
    protected $webServiceMock;

    // Test data
    protected array $listData;
    protected array $singleData;

    /**
     * Setup specific to equipment tests
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Add settings to prevent "chooseSelector" warning
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['univie_pure']['settings'] = [
            'chooseSelector' => 'default'
        ];

        // Define test data
        $this->defineTestData();

        // Create WebService mock
        $this->webServiceMock = $this->createMock(WebService::class);

        // Configure mock responses
        $this->configureMockWebService();

        // Initialize Equipments with the mocked WebService
        $this->equipments = new Equipments($this->webServiceMock);
    }

    /**
     * Define the test data
     */
    protected function defineTestData(): void
    {
        // Define equipment list data
        $this->listData = [
            'count' => 2,
            'offset' => 0,
            'items' => [
                [
                    'uuid' => 'equip-12345678-1234-1234-1234-123456789abc',
                    'name' => 'Test Equipment 1',
                    'description' => 'Description for test equipment 1',
                    'manufacturer' => 'Manufacturer A',
                    'model' => 'Model X100',
                    'renderings' => [
                        [
                            'html' => '<div class="equipment">Test Equipment 1 Rendering</div>'
                        ]
                    ],
                    'organizationalUnits' => [
                        ['name' => 'Department of Testing']
                    ]
                ],
                [
                    'uuid' => 'equip-87654321-4321-4321-4321-cba987654321',
                    'name' => 'Test Equipment 2',
                    'description' => 'Description for test equipment 2',
                    'manufacturer' => 'Manufacturer B',
                    'model' => 'Model Y200',
                    'renderings' => [
                        [
                            'html' => '<div class="equipment">Test Equipment 2 Rendering</div>'
                        ]
                    ],
                    'organizationalUnits' => [
                        ['name' => 'Department of Research']
                    ]
                ]
            ]
        ];

        // Define single equipment data
        $this->singleData = [
            'items' => [
                [
                    'uuid' => 'equip-12345678-1234-1234-1234-123456789abc',
                    'name' => 'Detailed Equipment',
                    'description' => 'Detailed description for test equipment',
                    'manufacturer' => 'Manufacturer X',
                    'model' => 'Model Z500',
                    'acquisitionDate' => '2022-05-15',
                    'renderings' => [
                        [
                            'html' => '<div class="equipment-detail">Test Equipment Detail Rendering</div>'
                        ]
                    ],
                    'organizationalUnits' => [
                        ['name' => 'Department of Testing']
                    ],
                    'accessRestrictions' => 'By appointment only',
                    'location' => 'Room 123, Building A'
                ]
            ]
        ];
    }

    /**
     * Configure mock responses
     */
    protected function configureMockWebService(): void
    {
        // Configure getJson to return equipment list data
        $this->webServiceMock
            ->method('getJson')
            ->willReturn($this->listData);

        // Configure getXml to return equipment list data (used by getEquipmentsList)
        $this->webServiceMock
            ->method('getXml')
            ->willReturn($this->listData);

        // Configure getSingleResponse to return single equipment data
        $this->webServiceMock
            ->method('getSingleResponse')
            ->willReturn($this->singleData);

        // Configure getAlternativeSingleResponse to return single equipment data
        $this->webServiceMock
            ->method('getAlternativeSingleResponse')
            ->willReturn($this->singleData);
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
    public function getEquipmentsListReturnsExpectedResult(): void
    {
        // Test settings
        $settings = [
            'pageSize' => 10,
            'rendering' => 'standard'
        ];

        // Call the method under test
        $result = $this->equipments->getEquipmentsList($settings, 1);

        // Debug output (uncomment if needed)
        // $this->debugDump($result);

        // Assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertEquals(2, $result['count']);

        // Verify equipments are processed correctly
        $this->assertCount(2, $result['items']);
        $this->assertArrayHasKey('uuid', $result['items'][0]);
    }

    /**
     * @test
     */
    public function getEquipmentsListWithFiltersAppliesFilters(): void
    {
        // Test settings with filtering
        $settings = [
            'pageSize' => 10,
            'narrowBySearch' => 'test search',
            'organizationalUnit' => 'Test Department'
        ];

        // Call the method under test
        $result = $this->equipments->getEquipmentsList($settings, 1);

        // Debug output (uncomment if needed)
        // $this->debugDump($result);

        // We can't fully test the filtering since we're using mocked responses,
        // but we can verify the result format is correct
        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
    }

    /**
     * @test
     */
    public function getEquipmentsListWithPaginationCalculatesOffsetCorrectly(): void
    {
        // Test settings
        $settings = [
            'pageSize' => 1,  // Set page size to 1 to test pagination
            'rendering' => 'standard'
        ];

        // Call the method under test for page 2
        $result = $this->equipments->getEquipmentsList($settings, 2);

        // Assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('offset', $result);
        $this->assertEquals(1, $result['offset']); // Should be (page-1) * pageSize = 1
    }

    /**
     * @test
     */
    public function getSingleEquipmentReturnsExpectedResult(): void
    {
        // Test UUID
        $uuid = 'equip-12345678-1234-1234-1234-123456789abc';

        // Call the method under test
        $result = $this->equipments->getSingleEquipment($uuid);

        // Assertions for single equipment
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }
}