<?php

namespace Univie\UniviePure\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use Univie\UniviePure\Endpoints\DataSets;
use Univie\UniviePure\Tests\Unit\Endpoints\FakeWebServiceDataSets;

require_once __DIR__ . '/FakeWebServiceDataSets.php';

final class DataSetsTest extends TestCase
{
    private DataSets $dataSets;

    protected function setUp(): void
    {
        $this->dataSets = new DataSets(new FakeWebServiceDataSets());
    }

    public function testGetSingleDataSetReturnsExpectedData(): void
    {
        $uuid = 'abc123';
        $lang = 'en_US';

        $result = $this->dataSets->getSingleDataSet($uuid, $lang);

        $expected = [
            'code' => '200',
            'data' => 'singleDataSet:' . $uuid . ':' . $lang,
        ];

        $this->assertSame($expected, $result, 'getSingleDataSet() did not return the expected result.');
    }

    public function testGetDataSetsListProcessesViewCorrectly(): void
    {
        $settings = [
            'pageSize'              => 1,  // Will default to 20 in the method
            'rendering'             => 'standard',
            'narrowBySearch'        => '',
            'filter'                => '',
            'chooseSelector'        => 0,
            'selectorProjects'      => '',
            'selectorOrganisations' => 'org1,org2',
            'includeSubUnits'       => 0,
        ];
        $currentPageNumber = 2;


        $result = $this->dataSets->getDataSetsList($settings, $currentPageNumber);

        $this->assertIsArray($result, 'getDataSetsList() should return an array.');
        $this->assertArrayHasKey('offset', $result, 'Offset key is missing in the result.');
        $this->assertArrayHasKey('items', $result, 'Items key is missing in the result.');

        $this->assertSame(1, $result['offset'], 'Offset calculation is incorrect.');
        $this->assertCount(2, $result['items'], 'Expected 2 items in the result.');

        foreach ($result['items'] as $index => $item) {
            $this->assertArrayHasKey('uuid', $item, "Item $index is missing UUID.");
            $this->assertArrayHasKey('link', $item, "Item $index is missing link.");
            $this->assertArrayHasKey('description', $item, "Item $index is missing description.");
            $this->assertArrayHasKey('renderings', $item, "Item $index is missing renderings.");
            $this->assertArrayHasKey('rendering', $item['renderings'], "Renderings array is missing 'rendering' key for item $index.");

            $this->assertIsArray($item['renderings']['rendering'], "renderings['rendering'] is not an array for item $index.");
        }
    }
}
