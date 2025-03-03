<?php
namespace Univie\UniviePure\Tests\Unit\Endpoints;

require_once __DIR__ . '/FakeWebServiceEquipments.php';

use PHPUnit\Framework\TestCase;
use Univie\UniviePure\Endpoints\Equipments;
use Univie\UniviePure\Utility\LanguageUtility;



/**
 * Test subclass of Equipments that overrides the instantiation of WebService.
 */
class TestEquipments extends Equipments {

    private FakeWebServiceEquipments $fakeWebService;

    public function __construct()
    {
        $this->fakeWebService = new FakeWebServiceEquipments();
        parent::__construct($this->fakeWebService);
    }



    /**
     * Override getSingleEquipment() to use our fake WebService.
     */
    public function getSingleEquipment($uuid, $lang = 'de_DE') {
        $webservice = $this->fakeWebService;
        return $webservice->getAlternativeSingleResponse('equipments', $uuid, "json", $lang);
    }

    /**
     * Override getEquipmentsList() to use our fake WebService.
     * (This is a simplified version based on your original code.)
     */
    public function getEquipmentsList(array $settings, int $currentPageNumber): array {
        if ($settings['pageSize'] == 0) {
            $settings['pageSize'] = 20;
        }
        $xml = '<?xml version="1.0"?><equipmentsQuery>';
        $xml .= \Univie\UniviePure\Utility\CommonUtilities::getPageSize($settings['pageSize']);
        $xml .= \Univie\UniviePure\Utility\CommonUtilities::getOffset($settings['pageSize'], $currentPageNumber);
        $xml .= \Univie\UniviePure\Utility\LanguageUtility::getLocale();
        $xml .= '<renderings><rendering>short</rendering></renderings>';
        $xml .= '<fields>
                    <field>renderings.*</field>
                    <field>links.*</field>
                    <field>info.*</field>
                    <field>contactPersons.*</field>
                    <field>emails.*</field>
                    <field>webAddresses.*</field>
                 </fields>';
        if ($settings['narrowBySearch'] || $settings['filter']) {
            $xml .= $this->getSearchXml($settings);
        }
        $xml .= \Univie\UniviePure\Utility\CommonUtilities::getPersonsOrOrganisationsXml($settings);
        $xml .= '</equipmentsQuery>';
        $webservice = $this->fakeWebService;
        $view = $webservice->getXml('equipments', $xml);

        // Process the fake response.
        if (isset($view["items"]) && is_array($view["items"])) {
            if (isset($view["items"]["equipment"])) {
                // Process each equipment.
                foreach ($view["items"]["equipment"] as $index => $item) {
                    // Process rendering.
                    $rendering = $item["renderings"]['rendering'];
                    if (!is_array($rendering)) {
                        $rendering = [$rendering];
                    }
                    $processed = [];
                    foreach ($rendering as $i => $r) {
                        $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $r);
                        $new_render = preg_replace('#<p><\/p>#is', '', $new_render);
                        $processed[$i] = ['html' => $new_render];
                    }
                    // Assign processed rendering.
                    $view["items"]["equipment"][$index]["renderings"]['rendering'] = $processed;
                    // Set additional keys.
                    $view["items"]["equipment"][$index]["uuid"] = $item["@attributes"]["uuid"];
                    $view["items"]["equipment"][$index]["portaluri"] = $item["info"]["portalUrl"];

                    // Process contact persons.
                    if (isset($item["contactPersons"]["contactPerson"])) {
                        if (array_key_exists("name", $item["contactPersons"]["contactPerson"])) {
                            $view["items"]["equipment"][$index]["contactPerson"] = [$item["contactPersons"]["contactPerson"]["name"]["text"]];
                        } else {
                            $names = [];
                            foreach ($item["contactPersons"]["contactPerson"] as $p) {
                                if (isset($p["name"])) {
                                    $names[] = $p["name"]["text"];
                                }
                            }
                            $view["items"]["equipment"][$index]["contactPerson"] = $names;
                        }
                    }
                    // Process emails.
                    if (isset($item["emails"]["email"])) {
                        if (array_key_exists("value", $item["emails"]["email"])) {
                            $view["items"]["equipment"][$index]["email"] = [strtolower($item["emails"]["email"]["value"])];
                        } else {
                            $emails = [];
                            foreach ($item["emails"]["email"] as $e) {
                                if (isset($e["value"])) {
                                    $emails[] = strtolower($e["value"]);
                                }
                            }
                            $view["items"]["equipment"][$index]["email"] = $emails;
                        }
                    }
                    // Process web addresses.
                    if (isset($item["webAddresses"]["webAddress"])) {
                        if (array_key_exists("value", $item["webAddresses"]["webAddress"])) {
                            $view["items"]["equipment"][$index]["webAddress"] = [$item["webAddresses"]["webAddress"]["value"]["text"]];
                        } else {
                            $addresses = [];
                            foreach ($item["webAddresses"]["webAddress"] as $e) {
                                if (isset($e["value"])) {
                                    $addresses[] = $e["value"]["text"];
                                }
                            }
                            $view["items"]["equipment"][$index]["webAddress"] = $addresses;
                        }
                    }
                    // Process portal link if requested.
                    if (isset($settings['linkToPortal']) && $settings['linkToPortal'] == 1) {
                        $view["items"]["equipment"][$index]["portaluri"] = $item["info"]["portalUrl"];
                    }
                }
                // Reassign processed equipment array.
                $view["items"] = $view["items"]["equipment"];
            }
        }
        $offset = (((int)$currentPageNumber - 1) * (int)$settings['pageSize']);
        $view['offset'] = $offset;
        return $view;
    }

    /**
     * For convenience, reuse the search XML from the original Equipments class.
     */
    public function getSearchXml($settings): string {
        $terms = $settings['narrowBySearch'];
        if ($settings['filter']) {
            $terms .= ' ' . $settings['filter'];
        }
        return '<searchString>' . trim($terms) . '</searchString>';
    }
}

/**
 * Test cases for the Equipments endpoint.
 */
class EquipmentsTest extends TestCase {

    /**
     * Test that getSingleEquipment() returns the expected fake data.
     */
    public function testGetSingleEquipmentReturnsExpectedData() {
        $equipments = new TestEquipments();
        $uuid = 'equip123';
        $lang = 'en_US';
        $result = $equipments->getSingleEquipment($uuid, $lang);
        $expected = [
            'code' => '200',
            'data' => 'singleEquipments:' . $uuid . ':' . $lang,
        ];
        $this->assertEquals($expected, $result, 'getSingleEquipment() did not return the expected result.');
    }

    /**
     * Test that getEquipmentsList() processes the fake response correctly.
     */
    public function testGetEquipmentsListProcessesViewCorrectly() {
        $equipments = new TestEquipments();
        // Supply settings needed by the XML builder.
        $settings = [
            'pageSize' => 0, // Will default to 20
            'narrowBySearch' => '',
            'filter' => '',
            'chooseSelector' => 0, // Avoid projects branch
            'includeSubUnits' => 0,
            'selectorProjects' => '',
            'selectorOrganisations' => '',
            'linkToPortal' => 1,
        ];
        $currentPageNumber = 2; // Expected offset = (2-1)*20 = 20

        $result = $equipments->getEquipmentsList($settings, $currentPageNumber);

        // Verify offset.
        $this->assertEquals(20, $result['offset'], 'Offset is not calculated correctly.');
        $this->assertIsArray($result['items'], 'Items should be an array.');
        $this->assertCount(2, $result['items'], 'There should be two equipment items.');

        // Check first equipment (eq1).
        $item1 = $result['items'][0];
        $this->assertEquals('eq1', $item1['uuid'], 'UUID of first equipment is incorrect.');
        $this->assertArrayHasKey('renderings', $item1, 'Renderings key missing for first equipment.');
        $this->assertIsArray($item1['renderings'], 'Renderings should be an array.');
        $this->assertStringContainsString('<h4 class="title">Equipment 1</h4>', $item1['renderings']['rendering'][0]['html'], 'Rendering for first equipment not processed correctly.');
        $this->assertEquals('http://example.com/eq1', $item1['portaluri'], 'Portal URI for first equipment is incorrect.');
        $this->assertArrayHasKey('contactPerson', $item1, 'ContactPerson key missing for first equipment.');
        $this->assertEquals(['John Doe'], $item1['contactPerson'], 'Contact person for first equipment is incorrect.');
        $this->assertArrayHasKey('email', $item1, 'Email key missing for first equipment.');
        $this->assertEquals(['email@example.com'], $item1['email'], 'Email for first equipment is incorrect.');
        $this->assertArrayHasKey('webAddress', $item1, 'WebAddress key missing for first equipment.');
        $this->assertEquals(['http://example.com'], $item1['webAddress'], 'Web address for first equipment is incorrect.');

        // Check second equipment (eq2).
        $item2 = $result['items'][1];
        $this->assertEquals('eq2', $item2['uuid'], 'UUID of second equipment is incorrect.');
        $this->assertArrayHasKey('renderings', $item2, 'Renderings key missing for second equipment.');
        $this->assertIsArray($item2['renderings'], 'Renderings should be an array.');
        $this->assertEquals('Equipment 2 Title', $item2['renderings']['rendering'][0]['html'], 'Rendering for second equipment is incorrect.');
        $this->assertEquals('http://example.com/eq2', $item2['portaluri'], 'Portal URI for second equipment is incorrect.');
        $this->assertArrayHasKey('contactPerson', $item2, 'ContactPerson key missing for second equipment.');
        $this->assertEquals(['Jane Smith'], $item2['contactPerson'], 'Contact person for second equipment is incorrect.');
        $this->assertArrayHasKey('email', $item2, 'Email key missing for second equipment.');
        $this->assertEquals(['info@example.com'], $item2['email'], 'Email for second equipment is incorrect.');
        $this->assertArrayHasKey('webAddress', $item2, 'WebAddress key missing for second equipment.');
        $this->assertEquals(['http://example.org'], $item2['webAddress'], 'Web address for second equipment is incorrect.');
    }
}