<?php
namespace Univie\UniviePure\Endpoints;

use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Utility\CommonUtilities;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/*
/*
 * (c) 2016 Christian Klettner <christian.klettner@univie.ac.at>, univie
 *          TYPO3-Team LUIS Uni-Hannover <typo3@luis.uni-hannover.de>, LUH
 *
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

class DataSets
{

    /**
     * query for single Proj
     * @return string xml
     */
    public function getSingleDataSet($uuid,$lang='de_DE')
    {
        $webservice = new WebService;
        return $webservice->getAlternativeSingleResponse('datasets', $uuid,  "json",  $lang);
    }


    /**
     * produce xml for the list query of projects
     * @return array $projects
     */
    public function getDataSetsList($settings,$currentPageNumber)
    {

        if($settings['pageSize'] == 0){
            $settings['pageSize'] = 20;
        }
        $xml = '<?xml version="1.0"?><dataSetsQuery>';
        //set page size:
        $xml .= CommonUtilities::getProjectsForDatasetsXml($settings);
        //set page size:
        $xml .= CommonUtilities::getPageSize($settings['pageSize']);
        //set offset:
        $xml .= CommonUtilities::getOffset($settings['pageSize'], $currentPageNumber);
       // $xml .= '<linkingStrategy>portalLinkingStrategy</linkingStrategy>';

        $xml .= CommonUtilities::getLocale();
        if ($settings['rendering'] == 'extended') {
            $xml .= '<renderings><rendering>short</rendering><rendering>detailsPortal</rendering></renderings>';
        }else{
            $xml .= '<renderings><rendering>short</rendering></renderings>';
        }
        $xml .= '<fields>
                    <field>*</field>
                    <field>info.portalUrl</field>
                 </fields>';

        //set ordering:
        $xml .= $this->getOrderingXml();

        //set filter:
        if ($settings['narrowBySearch'] || $settings['filter']) {
            $xml .= $this->getSearchXml($settings);
        }

        //either for organisations or for persons, both must not be submitted:
        $xml .= CommonUtilities::getPersonsOrOrganisationsXml($settings);
        $xml .= '</dataSetsQuery>';
        $webservice = new WebService;
        $view = $webservice->getXml('datasets', $xml);

        if (is_array($view)){
            if($view["count"] > 1){
                if (array_key_exists("items",$view)) {
                    if (is_array($view["items"])) {
                        if (array_key_exists("dataSet",$view["items"])) {
                            if (is_array($view["items"]["dataSet"])) {
                                foreach ($view["items"]["dataSet"] as $index => $items) {
                                    foreach ($items['renderings'] as $i => $x) {
                                        $uuid = $view["items"]["dataSet"][$index]["@attributes"]["uuid"];
                                        if (is_array($items["renderings"]['rendering'])){
                                            $new_render = implode("",$items["renderings"]['rendering']);
                                        }else{
                                            $new_render = $items["renderings"]['rendering'];
                                        }
                                        $new_render = mb_convert_encoding($new_render, "UTF-8");
                                        $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $new_render);
                                        $new_render = preg_replace('#<p class="type">(.*?)</p>#is', '', $new_render);
                                        $new_render = str_replace('<br />', ' ', $new_render);

                                        $view["items"][$index]["renderings"][$i]['html'] = $new_render;
                                        $view["items"][$index]["uuid"] = $uuid;
                                        $view["items"][$index]["link"] = $items['links']['link'];
                                        $view["items"][$index]["description"] = $items['descriptions']['description']['value']['text'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }else{
            $uuid = $view["items"]["dataSet"]["@attributes"]["uuid"];
            if (is_array($view["items"]["dataSet"]["renderings"]['rendering'])){
                $new_render = implode(" ",$view["items"]["dataSet"]["renderings"]['rendering']);
            }else{
                $new_render = $view["items"]["dataSet"]["renderings"]['rendering'];
            }
            $new_render = mb_convert_encoding($new_render, "UTF-8");
            $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $new_render);
            $new_render = preg_replace('#<p class="type">(.*?)</p>#is', '', $new_render);
            $new_render = str_replace('<br />', ' ', $new_render);
            $view["items"][0]["renderings"]['rendering']['html'] = $new_render;
            $view["items"][0]["uuid"] = $uuid;
            $view["items"][0]["link"] = $view["items"]["dataSet"]['links']['link'];
            $view["items"][0]["description"] = $view['items']['dataSet']['descriptions']['description']['value']['text'];
        }
        unset($view["items"]["dataSet"]);
        $offset = (((int)$currentPageNumber - 1) * (int)$settings['pageSize']);
        $view['offset'] = $offset;

        return $view;
    }


    /**
     * xml for search string
     * @return string xml
     */
    public function getSearchXml($settings)
    {
        $terms = $settings['narrowBySearch'];

        //combine the backend filter and the frontend form:
        if ($settings['filter']) {
            $terms .= ' ' . $settings['filter'];
        }
        return '<searchString>' . trim($terms) . '</searchString>';
    }


    /**
     * set the ordering
     * @return string xml
     */
    public function getOrderingXml()
    {

        $order = '-created';
        return '<orderings><ordering>' . $order . '</ordering></orderings>';
    }


}

?>
