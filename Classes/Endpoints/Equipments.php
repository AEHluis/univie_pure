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
     * query for single Proj
     * @return string xml
     */
    public function getSingleEquipment($uuid,$lang='de_DE')
    {
        return $this->webservice->getAlternativeSingleResponse('equipments', $uuid,  "json",  $lang);
    }


    /**
     * Produce XML for the list query of equipments
     * @param array $settings Configuration settings
     * @param int $currentPageNumber Current page number
     * @return array $equipments
     */
    public function getEquipmentsList(array $settings, int $currentPageNumber): array
    {
        // Set default page size if not provided
        $settings['pageSize'] = $this->getArrayValue($settings, 'pageSize', 20);

        $xml = '<?xml version="1.0"?><equipmentsQuery>';
        // Set page size
        $xml .= CommonUtilities::getPageSize($settings['pageSize']);
        // Set offset
        $xml .= CommonUtilities::getOffset($settings['pageSize'], $currentPageNumber);
        $xml .= LanguageUtility::getLocale();
        $xml .= '<renderings><rendering>short</rendering></renderings>';
        $xml .= '<fields>
                <field>renderings.*</field>
                <field>links.*</field>
                <field>info.*</field>
                <field>contactPersons.*</field>
                <field>emails.*</field>
                <field>webAddresses.*</field>
             </fields>';

        // Add search and filter if provided
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

        // Process equipment items
        if (isset($view['items'])) {
            // Standardize the data structure for both single and multiple equipment cases
            $equipmentItems = [];

            // Case 1: Multiple equipment items
            if (isset($view['items']['equipment']) && isset($view['items']['equipment'][0])) {
                $equipmentItems = $view['items']['equipment'];
            }
            // Case 2: Single equipment item
            else if (isset($view['items']['equipment'])) {
                $equipmentItems = [$view['items']['equipment']];
            }

            // Process each equipment item
            foreach ($equipmentItems as $index => $item) {
                // Extract basic information
                $uuid = $this->getNestedArrayValue($item, '@attributes.uuid', '');
                $rendering = $this->getNestedArrayValue($item, 'renderings.rendering', '');
                $portalUri = $this->getNestedArrayValue($item, 'info.portalUrl', '');

                // Transform rendering HTML
                $html = !empty($rendering) ? $this->transformRenderingHtml($rendering, []) : '';

                // Initialize the item data
                $view['items'][$index] = [
                    'uuid' => $uuid,
                    'renderings' => [
                        ['html' => $html]
                    ],
                    'contactPerson' => [],
                    'email' => [],
                    'webAddress' => []
                ];

                // Add portal URI if setting is enabled
                if ($this->arrayKeyExists('linkToPortal', $settings) && $settings['linkToPortal'] == 1) {
                    $view['items'][$index]['portaluri'] = $portalUri;
                }

                // Process contact persons
                $this->processContactPersons($item, $view['items'][$index]);

                // Process emails
                $this->processEmails($item, $view['items'][$index]);

                // Process web addresses
                $this->processWebAddresses($item, $view['items'][$index]);
            }

            // Remove the original equipment data to prevent duplication
            unset($view['items']['equipment']);
        }

        // Calculate the offset for pagination
        $view['offset'] = $this->calculateOffset((int)$settings['pageSize'], (int)$currentPageNumber);

        return $view;
    }

    /**
     * Process contact persons from equipment item
     * @param array $item Source equipment item
     * @param array &$target Target array to store contact persons
     */
    private function processContactPersons(array $item, array &$target): void
    {
        $contactPersons = $this->getArrayValue($item, 'contactPersons', []);
        $contactPerson = $this->getArrayValue($contactPersons, 'contactPerson', []);

        // Handle single contact person case
        $name = $this->getNestedArrayValue($contactPerson, 'name.text', '');
        if (!empty($name)) {
            $target['contactPerson'][] = $name;
            return;
        }

        // Handle multiple contact persons case
        if (is_array($contactPerson)) {
            foreach ($contactPerson as $person) {
                $personName = $this->getNestedArrayValue($person, 'name.text', '');
                if (!empty($personName)) {
                    $target['contactPerson'][] = $personName;
                }
            }
        }
    }

    /**
     * Process emails from equipment item
     * @param array $item Source equipment item
     * @param array &$target Target array to store emails
     */
    private function processEmails(array $item, array &$target): void
    {
        $emails = $this->getNestedArrayValue($item, 'emails.email', []);

        // Handle single email case
        $emailValue = $this->getNestedArrayValue($emails, 'value', '');
        if (!empty($emailValue)) {
            $target['email'][] = strtolower($emailValue);
            return;
        }

        // Handle multiple emails case
        if (is_array($emails)) {
            foreach ($emails as $email) {
                $singleEmail = $this->getNestedArrayValue($email, 'value', '');
                if (!empty($singleEmail)) {
                    $target['email'][] = strtolower($singleEmail);
                }
            }
        }
    }

    /**
     * Process web addresses from equipment item
     * @param array $item Source equipment item
     * @param array &$target Target array to store web addresses
     */
    private function processWebAddresses(array $item, array &$target): void
    {
        $webAddresses = $this->getNestedArrayValue($item, 'webAddresses.webAddress', []);

        // Handle single web address case
        $webAddressText = $this->getNestedArrayValue($webAddresses, 'value.text', '');
        if (!empty($webAddressText)) {
            $target['webAddress'][] = $webAddressText;
            return;
        }

        // Handle multiple web addresses case
        if (is_array($webAddresses)) {
            foreach ($webAddresses as $address) {
                // Try standard path
                $addressText = $this->getNestedArrayValue($address, 'value.text', '');

                // If not found, try alternative path that uses numbered index
                if (empty($addressText)) {
                    $addressText = $this->getNestedArrayValue($address, 'value.1.text', '');
                }

                if (!empty($addressText)) {
                    $target['webAddress'][] = $addressText;
                }
            }
        }
    }

}
