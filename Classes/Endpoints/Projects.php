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

class Projects extends Endpoints
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
    public function getSingleProject($uuid, $lang = 'de_DE')
    {
        return $this->webservice->getAlternativeSingleResponse('projects', $uuid, "json", $lang);
    }


    /**
     * produce xml for the list query of projects
     * @return array $projects
     */
    public function getProjectsList($settings, $currentPageNumber)
    {

        // Set default page size if not provided
        $settings['pageSize'] = $this->getArrayValue($settings, 'pageSize', 20);
        $xml = '<?xml version="1.0"?><projectsQuery>';
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
                    <field>descriptions.*</field>                    
                    <field>info.portalUrl</field>                    
                 </fields>';
        //set ordering:
        $xml .= $this->getOrderingXml($settings['orderProjects'] ?? '', '-startDate');

        //set filter:
        $xml .= $this->getFilterXml($settings['filterProjects']);

        $xml .= "<workflowSteps><workflowStep>validated</workflowStep></workflowSteps>";

        //either for organisations or for persons, both must not be submitted:
        $xml .= CommonUtilities::getPersonsOrOrganisationsXml($settings);

        //search AND filter:
        if ($this->getArrayValue($settings, 'narrowBySearch') || $this->getArrayValue($settings, 'filter')) {
            $xml .= $this->getSearchXml($settings);
        }

        $xml .= '</projectsQuery>';


        $view = $this->webservice->getXml('projects', $xml);
        if (!$view) {
            return [
                'error' => 'SERVER_NOT_AVAILABLE',
                'message' => LocalizationUtility::translate('error.server_unavailable', 'univie_pure')
            ];
        }

        if (is_array($view)) {
            if ($view["count"] > 0) {
                if (array_key_exists("items", $view)) {
                    if (is_array($view["items"])) {
                        if (array_key_exists("project", $view["items"])) {
                            if (is_array($view["items"]["project"])) {
                                // Normalize single project to array so the loop works consistently
                                if (array_key_exists("@attributes", $view["items"]["project"])) {
                                    $view["items"]["project"] = [$view["items"]["project"]];
                                }
                                foreach ($view["items"]["project"] as $index => $items) {
                                    if (array_key_exists("renderings", $items)) {
                                        if (is_array($items["renderings"])) {
                                            foreach ($items['renderings'] as $i => $x) {
                                                $uuid = $view["items"]["project"][$index]["@attributes"]["uuid"];
                                                $new_render = $items["renderings"]['rendering'];
                                                $new_render = $this->transformRenderingHtml($new_render, []);
                                                $view["items"][$index]["renderings"][$i]['html'] = $new_render;
                                                $view["items"][$index]["uuid"] = $uuid;
                                                // Extract links array for template iteration
                                                if (isset($items['links']['link'])) {
                                                    // Ensure links is always an array for template iteration
                                                    if (isset($items['links']['link'][0])) {
                                                        // Multiple links - already an array
                                                        $view["items"][$index]["links"] = $items['links']['link'];
                                                    } else {
                                                        // Single link - wrap in array
                                                        $view["items"][$index]["links"] = [$items['links']['link']];
                                                    }
                                                }
                                                $view["items"][$index]["description"] = $this->getNestedArrayValue($items, 'descriptions.description.value.text', '');

                                                if ((array_key_exists('linkToPortal', $settings)) && ($settings['linkToPortal'] == 1)) {
                                                    $view["items"][$index]["portaluri"] = $items['info']['portalUrl'];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            if ($view) {
                // Get the project data safely
                $project = $this->getNestedArrayValue($view, 'items.project', []);

                // Get UUID and rendering data
                $uuid = $this->getNestedArrayValue($project, '@attributes.uuid', '');
                $rendering = $this->getNestedArrayValue($project, 'renderings.rendering', '');

                // Transform the rendering HTML if it's not empty
                $new_render = '';
                if (!empty($rendering)) {
                    $new_render = $this->transformRenderingHtml($rendering, []);
                }

                // Assign basic values to the view array
                $view["items"][0]["renderings"]['rendering']['html'] = $new_render;
                $view["items"][0]["uuid"] = $uuid;

                // Assign additional data
                // Extract links array for template iteration
                if (isset($project['links']['link'])) {
                    // Ensure links is always an array for template iteration
                    if (isset($project['links']['link'][0])) {
                        // Multiple links - already an array
                        $view["items"][0]["links"] = $project['links']['link'];
                    } else {
                        // Single link - wrap in array
                        $view["items"][0]["links"] = [$project['links']['link']];
                    }
                }
                $view["items"][0]["description"] = $this->getNestedArrayValue($project, 'descriptions.description.value.text', '');

                // Add portal URI if setting is enabled
                if (isset($settings['linkToPortal']) && $settings['linkToPortal'] == 1) {
                    $view["items"][0]["portaluri"] = $this->getNestedArrayValue($project, 'info.portalUrl', '');
                }
            }
        }
        unset($view["items"]["project"]);

        if ($view) {
            $view['offset'] = $this->calculateOffset((int)$settings['pageSize'], (int)$currentPageNumber);
            return $view;
        } else {
            return [
                'error' => 'SERVER_NOT_AVAILABLE',
                'message' => LocalizationUtility::translate('error.server_unavailable', 'univie_pure')
            ];
        }

    }


    /**
     * set the filter
     * @return string xml
     */
    public function getFilterXml($filter)
    {
        if ($filter) {
            return '<projectStatus>' . $filter . '</projectStatus>';
        }
    }
}
