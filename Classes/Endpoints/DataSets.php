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

class DataSets extends Endpoints
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
    public function getSingleDataSet($uuid, $lang = 'de_DE')
    {
        return $this->webservice->getAlternativeSingleResponse('datasets', $uuid, "json", $lang);
    }


    /**
     * produce xml for the list query of projects
     * @return array $projects
     */
    public function getDataSetsList($settings, $currentPageNumber)
    {

        // Set default page size if not provided
        $settings['pageSize'] = $this->getArrayValue($settings, 'pageSize', 20);

        $xml = '<?xml version="1.0"?><dataSetsQuery>';
        //set page size:
        $xml .= CommonUtilities::getProjectsForDatasetsXml($settings);
        //set page size:
        $xml .= CommonUtilities::getPageSize($settings['pageSize']);
        //set offset:
        $xml .= CommonUtilities::getOffset($settings['pageSize'], $currentPageNumber);
        // $xml .= '<linkingStrategy>portalLinkingStrategy</linkingStrategy>';

        $xml .= LanguageUtility::getLocale();
        if ($settings['rendering'] == 'extended') {
            $xml .= '<renderings><rendering>short</rendering><rendering>detailsPortal</rendering></renderings>';
        } else {
            $xml .= '<renderings><rendering>short</rendering></renderings>';
        }
        $xml .= '<fields>
                    <field>*</field>
                    <field>info.portalUrl</field>
                 </fields>';

        //set ordering:
        $xml .= $this->getOrderingXml(null, '-created');

        //set filter:
        if ($this->getArrayValue($settings, 'narrowBySearch') || $this->getArrayValue($settings, 'filter')) {
            $xml .= $this->getSearchXml($settings);
        }

        //either for organisations or for persons, both must not be submitted:
        $xml .= CommonUtilities::getPersonsOrOrganisationsXml($settings);
        $xml .= '</dataSetsQuery>';
        $view = $this->webservice->getXml('datasets', $xml);

        if (!$view){
            return [
                'error' => 'SERVER_NOT_AVAILABLE',
                'message' => LocalizationUtility::translate('error.server_unavailable', 'univie_pure')
            ];
        }

        if (is_array($view)) {
            if ($view["count"] > 0) {
                if (array_key_exists("items", $view)) {
                    if (is_array($view["items"])) {
                        if (array_key_exists("dataSet", $view["items"])) {
                            if (is_array($view["items"]["dataSet"])) {
                                foreach ($view["items"]["dataSet"] as $index => $items) {
                                    $renderings = $this->getNestedArrayValue($items, 'renderings', []);
                                    if (is_array($renderings)) {
                                        foreach ($renderings as $i => $x) {
                                            $uuid = $this->getNestedArrayValue($view["items"]["dataSet"][$index], '@attributes.uuid', '');

                                            // Use the safely retrieved $renderings variable
                                            $rendering = $this->getNestedArrayValue($renderings, 'rendering', '');
                                            if (is_array($rendering)) {
                                                $new_render = implode("", $rendering);
                                            } else {
                                                $new_render = $rendering;
                                            }

                                            $new_render = mb_convert_encoding($new_render, "UTF-8");
                                            $new_render = $this->transformRenderingHtml($new_render, [
                                                'removeTypeParagraph' => true,
                                            ]);

                                            // Direct assignment for values we already have
                                            $view["items"][$index]["renderings"][$i]['html'] = $new_render;
                                            $view["items"][$index]["uuid"] = $uuid;

                                            // Use getNestedArrayValue for the nested array access
                                            $view["items"][$index]["link"] = $this->getNestedArrayValue($items, 'links.link', []);
                                            $view["items"][$index]["description"] = $this->getNestedArrayValue($items, 'descriptions.description.value.text', '');
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // Get the UUID and rendering data safely in one pass through the array
                $dataSet = $this->getNestedArrayValue($view, 'items.dataSet', []);
                $uuid = $this->getNestedArrayValue($dataSet, '@attributes.uuid', '');
                $rendering = $this->getNestedArrayValue($dataSet, 'renderings.rendering', '');

                // Process the rendering data - simplified with ternary operator
                $new_render = is_array($rendering) ? implode(" ", $rendering) : $rendering;

                // Chain the string transformations
                $new_render = $this->transformRenderingHtml(
                    mb_convert_encoding($new_render, "UTF-8"),
                    ['removeTypeParagraph' => true]
                );

                $view["items"][0]["renderings"]['rendering']['html'] = $new_render;
                $view["items"][0]["uuid"] = $uuid;
                $view["items"][0]["link"] = $this->getNestedArrayValue($view, 'dataSet.links.link', []);
                $view["items"][0]["description"] = $this->getNestedArrayValue($view, 'dataSet.descriptions.description.value.text', '');
            }
            unset($view["items"]["dataSet"]);

            $view['offset'] = $this->calculateOffset((int)$settings['pageSize'], (int)$currentPageNumber);

            return $view;
        }
    }
}
