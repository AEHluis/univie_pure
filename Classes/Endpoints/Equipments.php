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

        // Handle unavailable server or empty response
        if (!$view || !is_array($view)) {
            return [
                'error' => 'SERVER_NOT_AVAILABLE',
                'message' => LocalizationUtility::translate('error.server_unavailable', 'univie_pure')
            ];
        }

        $processedItems = [];
        // Get the list of equipment items. The API might return a single item directly or an array of items.
        $equipmentItems = $this->getNestedArrayValue($view, 'items.equipment', []);

        // If it's a single item not wrapped in an array, wrap it for consistent processing.
        // This heuristic checks if it's an associative array and not an empty array,
        // and doesn't appear to be a numerically indexed list.
        if (is_array($equipmentItems) && !empty($equipmentItems) && !array_is_list($equipmentItems)) {
            $equipmentItems = [$equipmentItems];
        } elseif (!is_array($equipmentItems)) {
            // If it's not an array at all (e.g., null from API for no results), treat as an empty list.
            $equipmentItems = [];
        }

        foreach ($equipmentItems as $index => $item) {
            $currentProcessedItem = [];

            // Process renderings
            // Safely get rendering content, which might be an array itself if multiple styles are returned
            $rendering = $this->getNestedArrayValue($item, 'renderings.rendering', '');
            $new_render = '';
            if (is_array($rendering)) {
                $new_render = implode(" ", $rendering);
            } else {
                $new_render = $rendering;
            }
            $new_render = $this->transformRenderingHtml(mb_convert_encoding($new_render, "UTF-8"), []);
            $currentProcessedItem['renderings'][0]['html'] = $new_render;

            // Assign UUID and portal URI
            $currentProcessedItem['uuid'] = $this->getNestedArrayValue($item, '@attributes.uuid', '');
            $currentProcessedItem['portaluri'] = $this->getNestedArrayValue($item, 'info.portalUrl', '');

            // Ensure the target array for processing functions is initialized
            if (!isset($processedItems[$index])) {
                $processedItems[$index] = [];
            }
            // Process contact persons, emails, web addresses, which modify $processedItems by reference
            $this->processContactPersons($item, $index, $processedItems);
            $this->processEmails($item, $index, $processedItems);
            $this->processWebAddresses($item, $index, $processedItems);

            // Merge current item's general details with what was added by the processing functions
            $processedItems[$index] = array_merge($processedItems[$index], $currentProcessedItem);

            if ($this->getArrayValue($settings, 'linkToPortal') == 1) {
                $processedItems[$index]['portaluri'] = $this->getNestedArrayValue($item, 'info.portalUrl', '');
            }
        }

        // Update the main $view array with processed data
        $view['count'] = $this->getArrayValue($view, 'count', 0);
        $view['items'] = $processedItems; // 'items' now consistently holds the processed list

        $view['offset'] = $this->calculateOffset((int)$settings['pageSize'], (int)$currentPageNumber);

        return $view;
    }

    private function processContactPersons(array $item, int $index, array &$view): void
    {
        $contactPersons = $this->getArrayValue($item, "contactPersons", []);
        $contactPerson = $this->getArrayValue($contactPersons, "contactPerson", []);

        if (!isset($view[$index]["contactPerson"])) {
            $view[$index]["contactPerson"] = [];
        }

        $name = $this->getNestedArrayValue($contactPerson, 'name.text', '');
        if (!empty($name)) {
            $view[$index]["contactPerson"][] = $name;
        } elseif (is_array($contactPerson)) {
            foreach ($contactPerson as $p) {
                $personName = $this->getNestedArrayValue($p, 'name.text', '');
                if (!empty($personName)) {
                    $view[$index]["contactPerson"][] = $personName;
                }
            }
        }
    }

    private function processEmails(array $item, int $index, array &$view): void
    {
        if (!isset($view[$index]["email"])) {
            $view[$index]["email"] = [];
        }

        $emails = $this->getNestedArrayValue($item, "emails.email", []);

        $emailValue = $this->getNestedArrayValue($emails, "value", '');
        if (!empty($emailValue)) {
            $view[$index]["email"][] = strtolower($emailValue);
        } elseif (is_array($emails)) {
            foreach ($emails as $e) {
                $singleEmail = $this->getNestedArrayValue($e, "value", '');
                if (!empty($singleEmail)) {
                    $view[$index]["email"][] = strtolower($singleEmail);
                }
            }
        }
    }

    private function processWebAddresses(array $item, int $index, array &$view): void
    {
        if (!isset($view[$index]["webAddress"])) {
            $view[$index]["webAddress"] = [];
        }

        $webAddresses = $this->getNestedArrayValue($item, "webAddresses.webAddress", []);

        $webAddressText = $this->getNestedArrayValue($webAddresses, "value.text", '');
        if (!empty($webAddressText)) {
            $view[$index]["webAddress"][] = $webAddressText;
        } elseif (is_array($webAddresses)) {
            foreach ($webAddresses as $e) {
                $singleWebAddressText = $this->getNestedArrayValue($e, "value.text", '');
                if (!empty($singleWebAddressText)) {
                    $view[$index]["webAddress"][] = $singleWebAddressText;
                }

                $multipleWebAddressText = $this->getNestedArrayValue($e, "value.1.text", '');
                if (empty($singleWebAddressText) && !empty($multipleWebAddressText)) {
                    $view[$index]["webAddress"][] = $multipleWebAddressText;
                }
            }
        }
    }
}
