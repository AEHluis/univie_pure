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

        //Important WorkflowSteps must be first and then getPersonsOrOrganisationsXml
        //workflow steps - only show approved and forApproval, exclude entries in progress
        $xml .= '<workflowSteps>
                    <workflowStep>approved</workflowStep>
                    <workflowStep>forApproval</workflowStep>
                 </workflowSteps>';
	 
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

        // Process equipment items like Projects endpoint does
        if (isset($view['count']) && $view['count'] > 0 && isset($view['items']['equipment'])) {
            $equipmentItems = $view['items']['equipment'];
            
            // Check if we have a single equipment or multiple
            if (isset($equipmentItems['@attributes'])) {
                // Single equipment - wrap it in an array
                $equipmentItems = [$equipmentItems];
            }
            
            // Process each equipment item in place
            foreach ($equipmentItems as $index => $item) {
                // Process renderings
                $rendering = $this->getNestedArrayValue($item, 'renderings.rendering', '');
                $new_render = '';
                if (is_array($rendering)) {
                    $new_render = implode(" ", $rendering);
                } else {
                    $new_render = $rendering;
                }
                $new_render = $this->transformRenderingHtml(mb_convert_encoding($new_render, "UTF-8"), []);
                
                // Update the view items array directly
                $view['items'][$index]['renderings']['rendering']['html'] = $new_render;
                $view['items'][$index]['uuid'] = $this->getNestedArrayValue($item, '@attributes.uuid', '');
                
                // Process contact persons
                $contactPersons = [];
                $contactPersonData = $this->getNestedArrayValue($item, 'contactPersons.contactPerson', []);
                if (isset($contactPersonData['name'])) {
                    // Single contact person
                    $name = $this->getNestedArrayValue($contactPersonData, 'name.text', '');
                    if (!empty($name)) {
                        $contactPersons[] = $name;
                    }
                } elseif (is_array($contactPersonData)) {
                    // Multiple contact persons
                    foreach ($contactPersonData as $person) {
                        $name = $this->getNestedArrayValue($person, 'name.text', '');
                        if (!empty($name)) {
                            $contactPersons[] = $name;
                        }
                    }
                }
                if (!empty($contactPersons)) {
                    $view['items'][$index]['contactPerson'] = $contactPersons;
                }
                
                // Process emails
                $emails = [];
                $emailData = $this->getNestedArrayValue($item, 'emails.email', []);
                if (isset($emailData['value'])) {
                    // Single email
                    $emails[] = strtolower($emailData['value']);
                } elseif (is_array($emailData)) {
                    // Multiple emails
                    foreach ($emailData as $email) {
                        if (isset($email['value'])) {
                            $emails[] = strtolower($email['value']);
                        }
                    }
                }
                if (!empty($emails)) {
                    $view['items'][$index]['email'] = $emails;
                }
                
                // Process web addresses
                $webAddresses = [];
                $webData = $this->getNestedArrayValue($item, 'webAddresses.webAddress', []);
                if (isset($webData['value'])) {
                    // Single web address
                    $webAddresses[] = $this->getNestedArrayValue($webData, 'value.text', '');
                } elseif (is_array($webData)) {
                    // Multiple web addresses
                    foreach ($webData as $web) {
                        $text = $this->getNestedArrayValue($web, 'value.text', '');
                        if (!empty($text)) {
                            $webAddresses[] = $text;
                        }
                    }
                }
                if (!empty($webAddresses)) {
                    $view['items'][$index]['webAddress'] = $webAddresses;
                }
                
                // Add portal URI if enabled
                if ($this->getArrayValue($settings, 'linkToPortal') == 1) {
                    $view['items'][$index]['portaluri'] = $this->getNestedArrayValue($item, 'info.portalUrl', '');
                }
            }
            
            // Remove the original equipment key to clean up the structure
            unset($view['items']['equipment']);
        }

        // Set offset for pagination
        $view['offset'] = $this->calculateOffset((int)$settings['pageSize'], (int)$currentPageNumber);

        return $view;
    }

}
