<?php
namespace Univie\UniviePure\Endpoints;
use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Utility\CommonUtilities;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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

class Projects
{

    /**
     * query for single Proj
     * @return string xml
     */
    public function getSingleProject($uuid,$lang='de_DE')
    {
        $webservice = new WebService;
        return $webservice->getAlternativeSingleResponse('projects', $uuid,  "json",  $lang);
    }


    /**
     * produce xml for the list query of projects
     * @return array $projects
     */
    public function getProjectsList($settings, $currentPageNumber)
    {

        if($settings['pageSize'] == 0){
            $settings['pageSize'] = 20;
        }
        $xml = '<?xml version="1.0"?><projectsQuery>';
        //set page size:
        $xml .= CommonUtilities::getPageSize($settings['pageSize']);

        //set offset:
        $xml .= CommonUtilities::getOffset($settings['pageSize'], $currentPageNumber);
        //$xml .= '<linkingStrategy>portalLinkingStrategy</linkingStrategy>';
        $xml .= CommonUtilities::getLocale();
        $xml .= '<renderings><rendering>short</rendering></renderings>';
        $xml .= '<fields>
                    <field>renderings.*</field>
                    <field>links.*</field>
                    <field>info.*</field>
                    <field>descriptions.*</field>                    
                    <field>info.portalUrl</field>                    
                 </fields>';
        //set ordering:
        $xml .= $this->getOrderingXml($settings['orderProjects']);

        //set filter:
        $xml .= $this->getFilterXml($settings['filterProjects']);

        $xml .= "<workflowSteps><workflowStep>validated</workflowStep></workflowSteps>";

        //either for organisations or for persons, both must not be submitted:
        $xml .= CommonUtilities::getPersonsOrOrganisationsXml($settings);

        //search AND filter:
        if ($settings['narrowBySearch'] || $settings['filter']) {
            $xml .= $this->getSearchXml($settings);
        }


        $xml .= '</projectsQuery>';

        $webservice = new WebService;

        $view = $webservice->getXml('projects', $xml);

        if (is_array($view)){
            if($view["count"] > 1){
                if (array_key_exists("items",$view)) {
                    if (is_array($view["items"])) {
                        if (array_key_exists("project",$view["items"])) {
                            if (is_array($view["items"]["project"])) {
                                foreach ($view["items"]["project"] as $index => $items) {
                                    if (array_key_exists("renderings",$items)) {
                                        if (is_array($items["renderings"])) {
                                            foreach ($items['renderings'] as $i => $x) {
                                                $uuid = $view["items"]["project"][$index]["@attributes"]["uuid"];
                                                $new_render = $items["renderings"]['rendering'];
                                                $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $new_render);
                                                $new_render = preg_replace('#<p><\/p>#is', '', $new_render);
                                                $new_render = str_replace('<br />', ' ', $new_render);
                                                $view["items"][$index]["renderings"][$i]['html'] = $new_render;
                                                $view["items"][$index]["uuid"] = $uuid;
                                                $view["items"][$index]["link"] = $items['links']['link'];
                                                $view["items"][$index]["description"] = $items['descriptions']['description']['value']['text'];
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
        }else{
            $uuid = $view["items"]["project"]["@attributes"]["uuid"];
            $new_render = $view["items"]["project"]["renderings"]['rendering'];
            $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $new_render);
            $new_render = preg_replace('#<p><\/p>#is', '', $new_render);
            $new_render = str_replace('<br />', ' ', $new_render);
            $view["items"][0]["renderings"]['rendering']['html'] = $new_render;
            $view["items"][0]["uuid"] = $uuid;
            $view["items"][0]["link"] = $view["items"]["project"]['links']['link'];
            $view["items"][0]["description"] = $view['items']['project']['descriptions']['description']['value']['text'];
            if ((array_key_exists('linkToPortal', $settings)) && ($settings['linkToPortal'] == 1)) {
                $view["items"][0]["portaluri"] = $view["items"]["project"]['info']['portalUrl'];
            }
        }
        unset($view["items"]["project"]);
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

?>
