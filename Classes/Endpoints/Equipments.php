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

        // Process equipment items like Projects endpoint does
        $equipmentItems = $this->getNestedArrayValue($view, 'items.equipment', null);
        if ($this->getArrayValue($view, 'count', 0) > 0 && $equipmentItems !== null) {
            
            // Check if we have a single equipment or multiple
            if (isset($equipmentItems['@attributes'])) {
                // Single equipment - wrap it in an array
                $equipmentItems = [$equipmentItems];
            }
            
            // Process each equipment item in place
            foreach ($equipmentItems as $index => $item) {
                // Skip invalid items
                if (!is_array($item)) {
                    continue;
                }

                // Process renderings with type safety
                $rendering = $this->getNestedArrayValue($item, 'renderings.rendering', '');
                $new_render = '';
                if (is_array($rendering)) {
                    // Filter out non-string values before imploding
                    $rendering = array_filter($rendering, 'is_string');
                    $new_render = implode(" ", $rendering);
                } elseif (is_string($rendering)) {
                    $new_render = $rendering;
                }

                // Ensure we have valid UTF-8 string before processing
                if (!empty($new_render)) {
                    $new_render = $this->transformRenderingHtml(mb_convert_encoding($new_render, "UTF-8"), []);
                }

                // Initialize nested structure safely using helper method
                $this->initializeNestedArray($view, "items.$index.renderings.rendering");

                // Update the view items array safely
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
                    $emailValue = $this->getArrayValue($emailData, 'value', '');
                    if (!empty($emailValue)) {
                        $emails[] = strtolower($emailValue);
                    }
                } elseif (is_array($emailData)) {
                    // Multiple emails
                    foreach ($emailData as $email) {
                        $emailValue = $this->getArrayValue($email, 'value', '');
                        if (!empty($emailValue)) {
                            $emails[] = strtolower($emailValue);
                        }
                    }
                }
                if (!empty($emails)) {
                    $view['items'][$index]['email'] = $emails;
                }
                
                // Process web addresses
                $webAddresses = [];
                $webData = $this->getNestedArrayValue($item, 'webAddresses.webAddress', []);
                if (!empty($webData) && is_array($webData)) {
                    // Check if single web address (has 'value' key directly)
                    if (isset($webData['value'])) {
                        $text = $this->getNestedArrayValue($webData, 'value.text', '');
                        if (!empty($text)) {
                            $webAddresses[] = $text;
                        }
                    } else {
                        // Multiple web addresses
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
                    $view['items'][$index]['webAddress'] = $webAddresses;
                }

                // Add portal URI if enabled
                if ($this->getArrayValue($settings, 'linkToPortal') == 1) {
                    $portalUri = $this->getNestedArrayValue($item, 'info.portalUrl', '');
                    if (!empty($portalUri)) {
                        $view['items'][$index]['portaluri'] = $portalUri;
                    }
                }
            }

            // Remove the original equipment key to clean up the structure
            if (isset($view['items']['equipment'])) {
                unset($view['items']['equipment']);
            }
        }

        // Set offset for pagination - ensure $view is still an array
        if (is_array($view)) {
            $view['offset'] = $this->calculateOffset(
                (int)$this->getArrayValue($settings, 'pageSize', 20),
                (int)$currentPageNumber
            );
        }

        return $view;
    }

}
