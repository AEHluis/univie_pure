<?php

namespace Univie\UniviePure\Endpoints;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Utility\CommonUtilities;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Utility\LanguageUtility;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class Equipments extends Endpoints
{

    private readonly WebService $webservice;

    public function __construct(WebService $webservice)
    {
        $this->webservice = $webservice;
    }

    /**
     * query for single equipment
     * @return string xml
     */
    public function getSingleEquipment($uuid, $lang = 'de_DE')
    {
        return $this->webservice->getAlternativeSingleResponse('equipments', $uuid, "json", $lang);
    }


    /**
     * produce xml for the list query of equipments
     * @return array $equipments
     */
    public function getEquipmentsList(array $settings, int $currentPageNumber)
    {
        // Set default page size if not provided
        $settings['pageSize'] = $this->getArrayValue($settings, 'pageSize', 20);

        $xml = '<?xml version="1.0"?><equipmentsQuery>';
        //set page size:
        $xml .= CommonUtilities::getPageSize($settings['pageSize']);

        //set offset:
        $xml .= CommonUtilities::getOffset($settings['pageSize'], $currentPageNumber);
        $xml .= LanguageUtility::getLocale('xml');
        $xml .= '<renderings><rendering>short</rendering></renderings>';
        $xml .= '<fields>
                <field>renderings.*</field>
                <field>links.*</field>
                <field>info.*</field>
                <field>contactPersons.*</field>
                <field>emails.*</field>
                <field>webAddresses.*</field>
             </fields>';
        //search AND filter:
        if ($this->getArrayValue($settings, 'narrowBySearch') || $this->getArrayValue($settings, 'filter')) {
            $xml .= $this->getSearchXml($settings);
        }

        // Add persons or organizations
        $xml .= CommonUtilities::getPersonsOrOrganisationsXml($settings);
        $xml .= '</equipmentsQuery>';

        // Get response from the web service
        $view = $this->webservice->getXml('equipments', $xml);

        // Handle unavailable server
        if (!$view) {
            return [
                'error' => 'SERVER_NOT_AVAILABLE',
                'message' => LocalizationUtility::translate('error.server_unavailable', 'univie_pure')
            ];
        }

        if (is_array($view["items"])) {
            if ($view['count'] > 1) {
                $equipmentItems = $this->getNestedArrayValue($view, 'items.equipment', []);
                if (is_array($equipmentItems)) {
                    foreach ($equipmentItems as $index => $items) {
                        foreach ($items['renderings'] as $i => $x) {
                            // Get values safely using getNestedArrayValue
                            $uuid = $this->getNestedArrayValue($view, "items.equipment.$index.@attributes.uuid", '');
                            $rendering = $this->getNestedArrayValue($items, "renderings.rendering", '');
                            $portalUri = $this->getNestedArrayValue($items, "info.portalUrl", '');

                            // Transform the rendering HTML if it's not empty
                            $new_render = !empty($rendering) ? $this->transformRenderingHtml($rendering, []) : '';

                            // Assign values to the view array
                            $view["items"][$index]["renderings"][$i]['html'] = $new_render;
                            $view["items"][$index]["uuid"] = $uuid;
                            $view["items"][$index]["portaluri"] = $portalUri;

                            // Process data using helper methods
                            $this->processContactPersons($items, $index, $view);
                            $this->processEmails($items, $index, $view);
                            $this->processWebAddresses($items, $index, $view);


                            if ($this->arrayKeyExists('linkToPortal', $settings) && $settings['linkToPortal'] == 1) {
                                $view["items"][$index]["portaluri"] = $this->getNestedArrayValue($items, 'info.portalUrl', '');
                            }
                        }
                    }
                }
            }
        } else {
            if (is_array($view["items"])) {
                if (is_array($view["items"]["equipment"])) {
                    // Get the UUID and rendering data safely
                    $uuid = $this->getNestedArrayValue($view, 'items.equipment.@attributes.uuid', '');
                    $rendering = $this->getNestedArrayValue($view, 'items.equipment.renderings.rendering', '');

                    // Transform the rendering HTML if it's not empty
                    $new_render = '';
                    if (!empty($rendering)) {
                        $new_render = $this->transformRenderingHtml($rendering, []);
                    }

                    // Assign values to the view array
                    $view["items"][0]["renderings"]["rendering"]['html'] = $new_render;
                    $view["items"][0]["uuid"] = $uuid;

                    // Handle contact persons for single item
                    $contactPersons = $this->getArrayValue($view["items"]["equipment"], "contactPersons", []);
                    $this->processContactPersonsSingle($contactPersons, $view);

                    // Handle emails for single item
                    $emails = $this->getArrayValue($view["items"]["equipment"], "emails", []);
                    $email = $this->getArrayValue($emails, "email", []);
                    if ($this->arrayKeyExists("value", $email)) {
                        $view["items"][0]["email"] = strtolower($email["value"]);
                    }

                    // Handle web addresses for single item
                    $webAddresses = $this->getArrayValue($view["items"]["equipment"], "webAddresses", []);
                    $webAddress = $this->getArrayValue($webAddresses, "webAddress", []);
                    if ($this->arrayKeyExists("value", $webAddress)) {
                        $view["items"][0]["webAddress"] = $webAddress["value"];
                    }

                    if ($this->arrayKeyExists('linkToPortal', $settings) && $settings['linkToPortal'] == 1) {
                        $view["items"][0]["portaluri"] = $this->getNestedArrayValue($view, 'items.equipment.info.portalUrl', '');
                    }
                }
            }
        }
        unset($view["items"]["equipment"]);
        $view['offset'] = $this->calculateOffset((int)$settings['pageSize'], (int)$currentPageNumber);

        return $view;
    }

    private function processContactPersons(array $items, int $index, array &$view): void
    {
        $contactPersons = $this->getArrayValue($items, "contactPersons", []);
        $contactPerson = $this->getArrayValue($contactPersons, "contactPerson", []);

        if (!isset($view["items"][$index]["contactPerson"])) {
            $view["items"][$index]["contactPerson"] = [];
        }

        $name = $this->getNestedArrayValue($contactPerson, 'name.text', '');
        if (!empty($name)) {
            $view["items"][$index]["contactPerson"][] = $name;
        } elseif (is_array($contactPerson)) {
            foreach ($contactPerson as $p) {
                $personName = $this->getNestedArrayValue($p, 'name.text', '');
                if (!empty($personName)) {
                    $view["items"][$index]["contactPerson"][] = $personName;
                }
            }
        }
    }

    private function processEmails(array $items, int $index, array &$view): void
    {
        if (!isset($view["items"][$index]["email"])) {
            $view["items"][$index]["email"] = [];
        }

        $emails = $this->getNestedArrayValue($items, "emails.email", []);

        $emailValue = $this->getNestedArrayValue($emails, "value", '');
        if (!empty($emailValue)) {
            $view["items"][$index]["email"][] = strtolower($emailValue);
        } elseif (is_array($emails)) {
            foreach ($emails as $e) {
                $singleEmail = $this->getNestedArrayValue($e, "value", '');
                if (!empty($singleEmail)) {
                    $view["items"][$index]["email"][] = strtolower($singleEmail);
                }
            }
        }
    }

    private function processWebAddresses(array $items, int $index, array &$view): void
    {
        if (!isset($view["items"][$index]["webAddress"])) {
            $view["items"][$index]["webAddress"] = [];
        }

        $webAddresses = $this->getNestedArrayValue($items, "webAddresses.webAddress", []);

        $webAddressText = $this->getNestedArrayValue($webAddresses, "value.text", '');
        if (!empty($webAddressText)) {
            $view["items"][$index]["webAddress"][] = $webAddressText;
        } elseif (is_array($webAddresses)) {
            foreach ($webAddresses as $e) {
                $singleWebAddressText = $this->getNestedArrayValue($e, "value.text", '');
                if (!empty($singleWebAddressText)) {
                    $view["items"][$index]["webAddress"][] = $singleWebAddressText;
                }

                $multipleWebAddressText = $this->getNestedArrayValue($e, "value.1.text", '');
                if (empty($singleWebAddressText) && !empty($multipleWebAddressText)) {
                    $view["items"][$index]["webAddress"][] = $multipleWebAddressText;
                }
            }
        }
    }

    private function processContactPersonsSingle(array $contactPersons, array &$view): void
    {
        $contactPerson = $this->getArrayValue($contactPersons, "contactPerson", []);

        if ($this->arrayKeyExists("name", $contactPerson)) {
            $view["items"][0]["contactPerson"][] = $contactPerson["name"]["text"];
        } elseif (is_array($contactPerson)) {
            foreach ($contactPerson as $p) {
                $nameText = $this->getNestedArrayValue($p, 'name.text', '');
                if (!empty($nameText)) {
                    $view["items"][0]["contactPerson"][] = $nameText;
                }
            }
        }
    }

}
