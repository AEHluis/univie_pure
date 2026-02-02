<?php

namespace Univie\UniviePure\Endpoints;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Utility\CommonUtilities;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Utility\LanguageUtility;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManager;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class Equipments extends Endpoints
{

    private readonly WebService $webservice;
    private readonly LoggerInterface $logger;

    public function __construct(WebService $webservice, ?LoggerInterface $logger = null)
    {
        $this->webservice = $webservice;
        $this->logger = $logger ?? GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Safely initialize nested array structure
     *
     * @param array $array The array to modify
     * @param string $path Dot-separated path (e.g., 'items.0.renderings.rendering')
     * @return array Modified array with initialized structure
     */
    private function initializeNestedArray(array &$array, string $path): void
    {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
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

        $xml = $this->buildEquipmentsQuery($settings, $currentPageNumber);

        // Get response from the web service
        $view = $this->webservice->getXml('equipments', $xml);

        // Comprehensive validation of API response
        if (!$view || !is_array($view)) {
            return [
                'error' => 'SERVER_NOT_AVAILABLE',
                'message' => LocalizationUtility::translate('error.server_unavailable', 'univie_pure'),
                'count' => 0,
                'items' => [],
                'offset' => 0
            ];
        }

        // Initialize default structure to prevent undefined array key errors
        if (!isset($view['items']) || !is_array($view['items'])) {
            $view['items'] = [];
        }
        if (!isset($view['count']) || !is_numeric($view['count'])) {
            $view['count'] = 0;
        }

        $totalCount = (int)$this->getArrayValue($view, 'count', 0);
        $items = $this->collectEquipmentItems($view, $settings);

        $pageSize = (int)$settings['pageSize'];
        if ($pageSize > 0 && count($items) < $pageSize && $totalCount > $this->calculateOffset($pageSize, $currentPageNumber) + $pageSize) {
            $nextPage = $currentPageNumber + 1;
            $nextOffset = $this->calculateOffset($pageSize, $nextPage);
            while (count($items) < $pageSize && $totalCount > $nextOffset) {
                $nextXml = $this->buildEquipmentsQuery($settings, $nextPage);
                $nextView = $this->webservice->getXml('equipments', $nextXml);
                if (!is_array($nextView)) {
                    break;
                }
                $moreItems = $this->collectEquipmentItems($nextView, $settings);
                if (!empty($moreItems)) {
                    $items = array_merge($items, $moreItems);
                }
                $nextPage++;
                $nextOffset = $this->calculateOffset($pageSize, $nextPage);
            }
            $items = array_slice($items, 0, $pageSize);
        }

        $view['items'] = $items;

        // Set offset for pagination - ensure $view is still an array
        if (is_array($view)) {
            $view['offset'] = $this->calculateOffset(
                (int)$this->getArrayValue($settings, 'pageSize', 20),
                (int)$currentPageNumber
            );
        }

        return $view;
    }

    private function buildEquipmentsQuery(array $settings, int $currentPageNumber): string
    {
        $xml = '<?xml version="1.0"?><equipmentsQuery>';
        //set page size:
        $xml .= CommonUtilities::getPageSize($settings['pageSize']);

        //set offset:
        $xml .= CommonUtilities::getOffset($settings['pageSize'], $currentPageNumber);
        $xml .= LanguageUtility::getLocale('xml');

        // Add renderings and fields BEFORE orderings (like ResearchOutput does)
        $xml .= '<renderings><rendering>short</rendering></renderings>';
        $xml .= '<fields>
                <field>renderings.*</field>
                <field>links.*</field>
                <field>info.*</field>
                <field>contactPersons.*</field>
                <field>emails.*</field>
                <field>webAddresses.*</field>
                <field>visibility.*</field>
             </fields>';

        //set ordering (MUST come AFTER renderings/fields per API schema):
        $ordering = $this->getArrayValue($settings, 'orderEquipments', 'title');
        $xml .= $this->getOrderingXml($ordering, 'title');

        //search AND filter:
        if ($this->getArrayValue($settings, 'narrowBySearch') || $this->getArrayValue($settings, 'filter')) {
            $xml .= $this->getSearchXml($settings);
        }

        // Add equipment types if enabled (try BEFORE workflowSteps in filter section)
        if (($this->getArrayValue($settings, 'narrowByEquipmentType', 0) == 1) &&
            ($this->getArrayValue($settings, 'selectorEquipmentType', '') != '')) {
            $xml .= $this->getEquipmentTypesXml($settings['selectorEquipmentType']);
        }

        // workflow steps - only show approved and forApproval, exclude entries in progress
        $xml .= '<workflowSteps>
                    <workflowStep>approved</workflowStep>
                    <workflowStep>forApproval</workflowStep>
                 </workflowSteps>';

        // Add persons or organizations
        $xml .= CommonUtilities::getPersonsOrOrganisationsXml($settings);

        $xml .= '</equipmentsQuery>';
        return $xml;
    }

    private function collectEquipmentItems(array $view, array $settings): array
    {
        $equipmentItems = $this->getNestedArrayValue($view, 'items.equipment', null);
        if ($this->getArrayValue($view, 'count', 0) <= 0 || $equipmentItems === null) {
            return [];
        }

        $isInCampus = class_exists(\T3luh\T3luhlib\PhpUtility::class)
            ? \T3luh\T3luhlib\PhpUtility::user_checkIP()
            : false;

        if (isset($equipmentItems['@attributes'])) {
            $equipmentItems = [$equipmentItems];
        }

        $items = [];
        foreach ($equipmentItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $processed = $this->processEquipmentItem($item, $settings, $isInCampus);
            if ($processed !== null) {
                $items[] = $processed;
            }
        }

        return $items;
    }

    private function processEquipmentItem(array $item, array $settings, bool $isInCampus): ?array
    {
        $visibilityKey = $this->getVisibilityKey($item);
        if (!$this->isVisibleForCurrentUser($visibilityKey, $isInCampus)) {
            return null;
        }

        $processed = [];

        // Process renderings with type safety
        $rendering = $this->getNestedArrayValue($item, 'renderings.rendering', '');
        $new_render = '';
        if (is_array($rendering)) {
            $rendering = array_filter($rendering, 'is_string');
            $new_render = implode(" ", $rendering);
        } elseif (is_string($rendering)) {
            $new_render = $rendering;
        }

        if (!empty($new_render)) {
            $new_render = $this->transformRenderingHtml(mb_convert_encoding($new_render, "UTF-8"), []);
        }

        $processed['renderings']['rendering']['html'] = $new_render;
        $processed['uuid'] = $this->getNestedArrayValue($item, '@attributes.uuid', '');

        // Process contact persons
        $contactPersons = [];
        $contactPersonData = $this->getNestedArrayValue($item, 'contactPersons.contactPerson', []);
        if (isset($contactPersonData['name'])) {
            $name = $this->getNestedArrayValue($contactPersonData, 'name.text', '');
            if (!empty($name)) {
                $contactPersons[] = $name;
            }
        } elseif (is_array($contactPersonData)) {
            foreach ($contactPersonData as $person) {
                $name = $this->getNestedArrayValue($person, 'name.text', '');
                if (!empty($name)) {
                    $contactPersons[] = $name;
                }
            }
        }
        if (!empty($contactPersons)) {
            $processed['contactPerson'] = $contactPersons;
        }

        // Process emails
        $emails = [];
        $emailData = $this->getNestedArrayValue($item, 'emails.email', []);
        if (isset($emailData['value'])) {
            $emailValue = $this->getArrayValue($emailData, 'value', '');
            if (!empty($emailValue)) {
                $emails[] = strtolower($emailValue);
            }
        } elseif (is_array($emailData)) {
            foreach ($emailData as $email) {
                $emailValue = $this->getArrayValue($email, 'value', '');
                if (!empty($emailValue)) {
                    $emails[] = strtolower($emailValue);
                }
            }
        }
        if (!empty($emails)) {
            $processed['email'] = $emails;
        }

        // Process web addresses
        $webAddresses = [];
        $webData = $this->getNestedArrayValue($item, 'webAddresses.webAddress', []);
        if (!empty($webData) && is_array($webData)) {
            if (isset($webData['value'])) {
                $text = $this->getNestedArrayValue($webData, 'value.text', '');
                if (!empty($text)) {
                    $webAddresses[] = $text;
                }
            } else {
                foreach ($webData as $web) {
                    if (is_array($web)) {
                        $text = $this->getNestedArrayValue($web, 'value.text', '');
                        if (!empty($text)) {
                            $webAddresses[] = $text;
                        }
                    }
                }
            }
        }
        if (!empty($webAddresses)) {
            $processed['webAddress'] = $webAddresses;
        }

        // Add portal URI if enabled
        if ($this->getArrayValue($settings, 'linkToPortal') == 1) {
            $portalUri = $this->getNestedArrayValue($item, 'info.portalUrl', '');
            if (!empty($portalUri)) {
                $processed['portaluri'] = $portalUri;
            }
        }

        return $processed;
    }

    /**
     * Generate XML for equipment types
     *
     * @param string $equipmentTypes Comma-separated list of equipment types
     * @return string XML for equipment types
     */
    protected function getEquipmentTypesXml(string $equipmentTypes): string
    {
        $xml = "<typeUris>";
        $types = explode(',', $equipmentTypes);

        foreach ((array)$types as $type) {
            if (strpos($type, "|")) {
                $tmp = explode("|", $type);
                $type = $tmp[0];
            }
            $xml .= '<typeUri>' . htmlspecialchars($type, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</typeUri>';
        }
        $xml .= '</typeUris>';
        return $xml;
    }

}
