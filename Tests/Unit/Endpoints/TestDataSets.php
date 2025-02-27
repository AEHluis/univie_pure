<?php

namespace Univie\UniviePure\Tests\Unit\Endpoints;

require_once __DIR__ . '/FakeWebServiceDataSets.php';

use Univie\UniviePure\Endpoints\DataSets;

class TestDataSets extends DataSets
{
    /**
     * Instead of "new WebService", return our FakeWebService.
     */
    protected function createWebService()
    {
        return new FakeWebServiceDataSets();
    }

    public function getSingleDataSet($uuid, $lang = 'de_DE')
    {
        $webservice = $this->createWebService();
        return $webservice->getAlternativeSingleResponse('datasets', $uuid, "json", $lang);
    }

    public function getDataSetsList($settings, $currentPageNumber)
    {
        if ($settings['pageSize'] == 0) {
            $settings['pageSize'] = 20;
        }
        $xml = '<?xml version="1.0"?><dataSetsQuery>';
        // Build XML using CommonUtilities.
        $xml .= \Univie\UniviePure\Utility\CommonUtilities::getProjectsForDatasetsXml($settings);
        $xml .= \Univie\UniviePure\Utility\CommonUtilities::getPageSize($settings['pageSize']);
        $xml .= \Univie\UniviePure\Utility\CommonUtilities::getOffset($settings['pageSize'], $currentPageNumber);
        $xml .= \Univie\UniviePure\Utility\CommonUtilities::getLocale();
        if ($settings['rendering'] == 'extended') {
            $xml .= '<renderings><rendering>short</rendering><rendering>detailsPortal</rendering></renderings>';
        } else {
            $xml .= '<renderings><rendering>short</rendering></renderings>';
        }
        $xml .= '<fields>
                <field>*</field>
                <field>info.portalUrl</field>
             </fields>';
        // Ordering XML.
        $xml .= $this->getOrderingXml();
        // Filter/search XML.
        if ($settings['narrowBySearch'] || $settings['filter']) {
            $xml .= $this->getSearchXml($settings);
        }
        $xml .= \Univie\UniviePure\Utility\CommonUtilities::getPersonsOrOrganisationsXml($settings);
        $xml .= '</dataSetsQuery>';

        $webservice = $this->createWebService();
        $view = $webservice->getXml('datasets', $xml);

        if (is_array($view)) {
            if ($view["count"] > 1) {
                if (isset($view["items"]) && is_array($view["items"]) && isset($view["items"]["dataSet"])) {
                    if (is_array($view["items"]["dataSet"])) {
                        // Process each dataset item.
                        foreach ($view["items"]["dataSet"] as $index => $item) {
                            // Ensure we work with an array for renderings.
                            $renderings = $item["renderings"]['rendering'];
                            if (!is_array($renderings)) {
                                $renderings = [$renderings];
                            }
                            $processedRenderings = [];
                            foreach ($renderings as $i => $r) {
                                $new_render = mb_convert_encoding($r, "UTF-8");
                                $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $new_render);
                                $new_render = preg_replace('#<p class="type">(.*?)</p>#is', '', $new_render);
                                $new_render = str_replace('<br />', ' ', $new_render);
                                $processedRenderings[$i] = ['html' => $new_render];
                            }
                            // Replace the original renderings with the processed array.
                            $view["items"]["dataSet"][$index]["renderings"]['rendering'] = $processedRenderings;
                            // Set other keys.
                            $view["items"]["dataSet"][$index]["uuid"] = $item["@attributes"]["uuid"];
                            $view["items"]["dataSet"][$index]["link"] = $item['links']['link'];
                            $view["items"]["dataSet"][$index]["description"] = $item['descriptions']['description']['value']['text'];
                        }
                    }
                }
            } else {
                // Single dataset processing.
                $item = $view["items"]["dataSet"];
                $uuid = $item["@attributes"]["uuid"];
                $rendering = $item["renderings"]['rendering'];
                if (!is_array($rendering)) {
                    $rendering = [$rendering];
                }
                $processedRenderings = [];
                foreach ($rendering as $i => $r) {
                    $new_render = mb_convert_encoding($r, "UTF-8");
                    $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $new_render);
                    $new_render = preg_replace('#<p class="type">(.*?)</p>#is', '', $new_render);
                    $new_render = str_replace('<br />', ' ', $new_render);
                    $processedRenderings[$i] = ['html' => $new_render];
                }
                $view["items"]["dataSet"]["renderings"]['rendering'] = $processedRenderings;
                $view["items"]["dataSet"]["uuid"] = $uuid;
                $view["items"]["dataSet"]["link"] = $item['links']['link'];
                $view["items"]["dataSet"]["description"] = $item['descriptions']['description']['value']['text'];
            }
        }
        // Reassign processed dataSet to items.
        if (isset($view["items"]["dataSet"])) {
            $view["items"] = $view["items"]["dataSet"];
        }
        $offset = (((int)$currentPageNumber - 1) * (int)$settings['pageSize']);
        $view['offset'] = $offset;
        return $view;
    }

}
