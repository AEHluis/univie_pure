<?php
namespace Univie\UniviePure\Tests\Unit\Endpoints;
require_once __DIR__ . '/FakeWebServiceProjects.php';
require_once __DIR__ . '/TestProjects.php';

use PHPUnit\Framework\TestCase;
use Univie\UniviePure\Endpoints\Projects;
use Univie\UniviePure\Utility\CommonUtilities;

/**
 * PHPUnit tests for the Projects endpoint.
 */
class ProjectsTest extends TestCase {

    /**
     * Test that getSingleProject() returns the expected fake response.
     */
    public function testGetSingleProjectReturnsExpectedData() {
        $projects = new TestProjects();
        $uuid = 'proj123';
        $lang = 'en_US';
        $result = $projects->getSingleProject($uuid, $lang);
        $expected = [
            'code' => '200',
            'data' => 'singleProjects:' . $uuid . ':' . $lang,
        ];
        $this->assertEquals($expected, $result, 'getSingleProject() did not return the expected result.');
    }

    /**
     * Test that getProjectsList() processes the fake XML response correctly.
     */
    public function testGetProjectsListProcessesViewCorrectly() {
        $projects = new TestProjects();
        // Supply settings needed by the XML builder.
        $settings = [
            'pageSize' => 0, // defaults to 20
            'narrowBySearch' => '',
            'filter' => '',
            'orderProjects' => '',  // default ordering (-startDate)
            'filterProjects' => '',
            // Additional keys required by CommonUtilities:
            'chooseSelector' => 0,
            'selectorProjects' => '',
            'selectorOrganisations' => '',
            'includeSubUnits' => 0,
            'linkToPortal' => 1,
        ];
        $currentPageNumber = 2; // Expected offset = (2-1)*20 = 20

        $result = $projects->getProjectsList($settings, $currentPageNumber);

        // Verify the calculated offset.
        $this->assertEquals(20, $result['offset'], 'Offset is not calculated correctly.');

        // Check that the processed view contains an "items" array with two project items.
        $this->assertIsArray($result['items'], 'Items should be an array.');
        $this->assertCount(2, $result['items'], 'There should be two project items.');

        // Verify processing of the first project.
        $item1 = $result['items'][0];
        $this->assertEquals('proj1', $item1['uuid'], 'UUID of first project is incorrect.');
        $this->assertEquals('http://example.com/proj1', $item1['link'], 'Link of first project is incorrect.');
        $this->assertEquals('Description 1', $item1['description'], 'Description of first project is incorrect.');

        // First check if the renderings array has the expected structure
        $this->assertArrayHasKey('renderings', $item1, 'Renderings key missing for first project.');
        $this->assertIsArray($item1['renderings'], 'Renderings should be an array.');
        $this->assertArrayHasKey(0, $item1['renderings'], 'Renderings index 0 missing for first project.');
        $this->assertArrayHasKey('html', $item1['renderings'][0], 'HTML key missing in rendering for first project.');
        $this->assertNotNull($item1['renderings'][0]['html'], 'HTML content is null for first project.');

        // Now test the content
        $this->assertStringContainsString('<h4 class="title">Project 1 Title</h4>', $item1['renderings'][0]['html'], 'Rendering for first project not processed correctly.');
        $this->assertEquals('http://example.com/portal1', $item1['portaluri'], 'Portal URI for first project is incorrect.');

        // Verify processing of the second project.
        $item2 = $result['items'][1];
        $this->assertEquals('proj2', $item2['uuid'], 'UUID of second project is incorrect.');
        $this->assertEquals('http://example.com/proj2', $item2['link'], 'Link of second project is incorrect.');
        $this->assertEquals('Description 2', $item2['description'], 'Description of second project is incorrect.');

        // First check if the renderings array has the expected structure
        $this->assertArrayHasKey('renderings', $item2, 'Renderings key missing for second project.');
        $this->assertIsArray($item2['renderings'], 'Renderings should be an array.');
        $this->assertArrayHasKey(0, $item2['renderings'], 'Renderings index 0 missing for second project.');
        $this->assertArrayHasKey('html', $item2['renderings'][0], 'HTML key missing in rendering for second project.');
        $this->assertNotNull($item2['renderings'][0]['html'], 'HTML content is null for second project.');

        // Now test the content
        $this->assertEquals('Project 2 Title', $item2['renderings'][0]['html'], 'Rendering for second project is incorrect.');
        $this->assertEquals('http://example.com/portal2', $item2['portaluri'], 'Portal URI for second project is incorrect.');
    }
}
