<?php
namespace Univie\UniviePure\Endpoints;

use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Utility\CommonUtilities;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/*
 * (c) TYPO3-Team LUIS Uni-Hannover <typo3@luis.uni-hannover.de>, LUH
 *
 * This file is part of the TYPO3 CMS porject.
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

class Equipments
{



    /**
     * query for single Proj
     * @return string xml
     */
    public function getSingleEquipment($uuid,$lang='de_DE')
    {
        $webservice = new WebService;
        return $webservice->getAlternativeSingleResponse('equipments', $uuid,  "json",  $lang);
    }

    /**
     * produce xml for the list query of equipments
     * @return array $equipments
     */
    public function getEquipmentsList($settings,$currentPageNumber)
    {

        if ($settings['pageSize'] == 0) {
            $settings['pageSize'] = 20;
        }
        $xml = '<?xml version="1.0"?><equipmentsQuery>';
        //set page size:
        $xml .= CommonUtilities::getPageSize($settings['pageSize']);

        //set offset:
        $xml .= CommonUtilities::getOffset($settings['pageSize'], $currentPageNumber);
        $xml .= CommonUtilities::getLocale();
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
        if ($settings['narrowBySearch'] || $settings['filter']) {
            $xml .= $this->getSearchXml($settings);
        }
        $xml .= CommonUtilities::getPersonsOrOrganisationsXml($settings);

        $xml .= '</equipmentsQuery>';
        $webservice = new WebService;
        $view = $webservice->getXml('equipments', $xml);

        if (is_array($view["items"])) {
            if ($view['count'] > 1) {
                if (is_array($view["items"]["equipment"])) {
                    foreach ($view["items"]["equipment"] as $index=>$items) {
                        foreach ($items['renderings'] as $i=>$x) {
                            $uuid = $view["items"]["equipment"][$index]["@attributes"]["uuid"];
                            $new_render = $items["renderings"]['rendering'];
                            $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $new_render);
                            $new_render = preg_replace('#<p><\/p>#is', '', $new_render);
                            $view["items"][$index]["renderings"][$i]['html'] = $new_render;
                            $view["items"][$index]["uuid"] = $uuid;
                            $view["items"][$index]["portaluri"] = $items["info"]["portalUrl"];
                            if(array_key_exists("name",$items["contactPersons"]["contactPerson"])){
                                $view["items"][$index]["contactPerson"][] = $items["contactPersons"]["contactPerson"]["name"]["text"];
                            }else{
                                foreach ($items["contactPersons"]["contactPerson"] as $p) {
                                    if(array_key_exists("name", $p)) {
                                        $view["items"][$index]["contactPerson"][] = $p["name"]["text"];
                                    }
                                }
                            }
                            if(is_array($items["emails"])) {
                                if (array_key_exists("value", $items["emails"]["email"])) {
                                    $view["items"][$index]["email"][] = strtolower($items["emails"]["email"]["value"]);
                                } else {
                                    foreach ($items["emails"]["email"] as $e) {
                                        if (array_key_exists("value", $p)) {
                                            $view["items"][$index]["email"][] = strtolower($p["value"]);
                                        }
                                    }
                                }
                            }
                            if(is_array($items["webAddresses"])) {
                                if (array_key_exists("value", $items["webAddresses"]["webAddress"])) {
                                    $view["items"][$index]["webAddress"][] = $items["webAddresses"]["webAddress"]["value"]["text"];
                                } else {
                                    foreach ($items["webAddresses"]["webAddress"] as $e) {
                                        if (array_key_exists("value", $e)) {
                                            $view["items"][$index]["webAddress"][] = $p["value"][1]["text"];
                                        }
                                    }
                                }
                            }
                            if (array_key_exists('linkToPortal', $settings)){
                                if ($settings['linkToPortal'] == 1){
                                    $view["items"][$index]["portaluri"] = $items["info"]["portalUrl"];
                                }
                            }
                        }
                    }
                }
            }
        }else{
            if (is_array($view["items"])) {
                if (is_array($view["items"]["equipment"])) {
                    $uuid = $view["items"]["equipment"]["@attributes"]["uuid"];
                    $new_render = $view["items"]["equipment"]["renderings"]['rendering'];
                    $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $new_render);
                    $new_render = preg_replace('#<p><\/p>#is', '', $new_render);
                    $view["items"][0]["renderings"]["rendering"]['html'] = $new_render;
                    $view["items"][0]["uuid"] = $uuid;
                    if(array_key_exists("name", $view["items"]["equipment"]["contactPersons"]["contactPerson"])){
                        $view["items"][0]["contactPerson"][] = $view["items"]["equipment"]["contactPersons"]["contactPerson"]["name"]["text"];
                    }else{
                        foreach ($view["items"]["equipment"]["contactPersons"]["contactPerson"] as $p) {
                            if(array_key_exists("name", $p)) {
                                $view["items"][0]["contactPerson"][] = $p["name"]["text"];
                            }
                        }
                    }

                    if(array_key_exists("value",$view["items"]["equipment"]["emails"]["email"])){
                        $view["items"][0]["email"] = strtolower($view["items"]["equipment"]["emails"]["email"]["value"]);
                    }
                    if(array_key_exists("value",$view["items"]["equipment"]["webAddresses"]["webAddress"])){
                        $view["items"][0]["webAddress"] = $view["items"]["equipment"]["webAddresses"]["webAddress"]["value"];
                    }
                    if ((array_key_exists('linkToPortal', $settings)) && ($settings['linkToPortal'] == 1)) {
                        $view["items"][0]["portaluri"] = $view["items"]["equipment"]["info"]["portalUrl"];
                    }
                }
            }
        }
        unset($view["items"]["equipment"]);
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
    public function getOrderingXml($order)
    {
        if (!$order) {
            $order = '-startDate';
        }//default
        return '<orderings><ordering>' . $order . '</ordering></orderings>';
    }
}

?>
