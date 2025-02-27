<?php

namespace Univie\UniviePure\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use Univie\UniviePure\Endpoints\DataSets;
use Univie\UniviePure\Tests\Unit\Endpoints\FakeWebServiceDataSets;

/**
 * Require your fake web service implementation that extends WebService
 * and overrides the methods for testing.
 * Adjust the path if needed.
 */
require_once __DIR__ . '/FakeWebServiceDataSets.php';

/**
 * A test-specific subclass of DataSets which injects a fake WebService,
 * avoiding the ArgumentCountError (since DataSets normally
 * requires a WebService in its constructor).
 */
class TestDataSets extends DataSets
{
    public function __construct()
    {
        // Provide the fake web service to the parent constructor
        parent::__construct(new FakeWebServiceDataSets());
    }
}

/**
 * The actual PHPUnit test class for DataSets functionality.
 */
final class DataSetsTest extends TestCase
{
    public function testGetSingleDataSetReturnsExpectedData(): void
    {
        $dataSets = new TestDataSets();

        $uuid = 'abc123';
        $lang = 'en_US';
        $result = $dataSets->getSingleDataSet($uuid, $lang);

        $expected = [
            'code' => '200',
            'data' => 'singleDataSet:' . $uuid . ':' . $lang,
        ];

        $this->assertEquals(
            $expected,
            $result,
            'getSingleDataSet() did not return the expected result.'
        );
    }

    public function testGetDataSetsListProcessesViewCorrectly(): void
    {
        $dataSets = new TestDataSets();

        // Provide all keys that CommonUtilities expects
        // (so we avoid "undefined array key" warnings).
        $settings = [
            'pageSize'             => 0,  // Will default to 20 in the method
            'rendering'            => 'standard',
            'narrowBySearch'       => '',
            'filter'               => '',
            'chooseSelector'       => 0,
            'selectorProjects'     => '',
            'selectorOrganisations'=> 'org1,org2',
            'includeSubUnits'      => 0,
        ];
        $currentPageNumber = 2;

        // Run the code under test
        $result = $dataSets->getDataSetsList($settings, $currentPageNumber);

        // Check pagination offset
        $this->assertEquals(
            20,
            $result['offset'],
            'Offset is not calculated correctly.'
        );

        $this->assertIsArray($result['items'], 'Items should be an array.');
        $this->assertCount(
            2,
            $result['items'],
            'There should be two items after processing.'
        );

        // --- Check the first item ---
        $item1 = $result['items'][0];
        $this->assertEquals('uuid1', $item1['uuid'], 'UUID of first item is incorrect.');
        $this->assertEquals('link1', $item1['link'], 'Link of first item is incorrect.');
        $this->assertEquals('Description 1', $item1['description'], 'Description of first item is incorrect.');

        $this->assertArrayHasKey(
            'rendering',
            $item1['renderings'],
            'Missing "rendering" key in first item’s renderings.'
        );
        $this->assertIsArray(
            $item1['renderings']['rendering'],
            'renderings["rendering"] is not an array for first item.'
        );

        $html1 = $item1['renderings']['rendering']['html'];
        $this->assertStringContainsString(
            '<h4 class="title">Title 1</h4>',
            $html1,
            'Title tag was not converted.'
        );
        $this->assertStringNotContainsString(
            '<p class="type">',
            $html1,
            'Type paragraph should have been removed.'
        );
        $this->assertStringNotContainsString(
            '<br />',
            $html1,
            'Line breaks should have been replaced.'
        );

        // --- Check the second item ---
        $item2 = $result['items'][1];
        $this->assertEquals('uuid2', $item2['uuid'], 'UUID of second item is incorrect.');
        $this->assertEquals('link2', $item2['link'], 'Link of second item is incorrect.');
        $this->assertEquals('Description 2', $item2['description'], 'Description of second item is incorrect.');

        $this->assertArrayHasKey(
            'rendering',
            $item2['renderings'],
            'Missing "rendering" key in second item’s renderings.'
        );
        $this->assertIsArray(
            $item2['renderings']['rendering'],
            'renderings["rendering"] is not an array for second item.'
        );

        $html2 = $item2['renderings']['rendering']['html'];
        $this->assertEquals(
            'Simple Title 2',
            $html2,
            'Rendering of second item is incorrect.'
        );
    }
}