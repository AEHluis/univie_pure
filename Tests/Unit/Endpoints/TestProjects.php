<?php

namespace Univie\UniviePure\Tests\Unit\Endpoints;

use Univie\UniviePure\Endpoints\Projects;
use Univie\UniviePure\Utility\CommonUtilities;
use Univie\UniviePure\Utility\LanguageUtility;


/**
 * Test subclass of Projects that forces the use of our fake WebService.
 */
class TestProjects extends Projects
{

    private FakeWebServiceEquipments $fakeWebService;

    public function __construct()
    {
        $this->fakeWebService = new FakeWebServiceEquipments();
        parent::__construct($this->fakeWebService);
    }

    /**
     * Override getSingleProject() to use our fake service.
     */
    public function getSingleProject($uuid, $lang = 'de_DE')
    {
        $webservice = $this->fakeWebService;
        return $webservice->getAlternativeSingleResponse('projects', $uuid, "json", $lang);
    }

    /**
     * Override getProjectsList() to use our fake service.
     */
    public function getProjectsList($settings, $currentPageNumber)
    {
        if ($settings['pageSize'] == 0) {
            $settings['pageSize'] = 20;
        }
        $xml = '<?xml version="1.0"?><projectsQuery>';
        $xml .= CommonUtilities::getPageSize($settings['pageSize']);
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
        $xml .= $this->getOrderingXml($settings['orderProjects']);
        $xml .= $this->getFilterXml($settings['filterProjects']);
        $xml .= "<workflowSteps><workflowStep>validated</workflowStep></workflowSteps>";
        $xml .= CommonUtilities::getPersonsOrOrganisationsXml($settings);
        if ($settings['narrowBySearch'] || $settings['filter']) {
            $xml .= $this->getSearchXml($settings);
        }
        $xml .= '</projectsQuery>';

        $webservice = $this->fakeWebService;
        $view = $webservice->getXml('projects', $xml);

// Process the fake response.
        if (is_array($view) && isset($view["items"]["project"])) {
            $items = $view["items"]["project"];
            foreach ($items as $index => $item) {
                $uuid = $item["@attributes"]["uuid"];
                $rendering = $item["renderings"]['rendering'];
                if (!is_array($rendering)) {
                    $rendering = [$rendering];
                }
                $processed = [];
                foreach ($rendering as $i => $r) {
                    $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $r);
                    $new_render = preg_replace('#<p><\/p>#is', '', $new_render);
                    $new_render = str_replace('<br />', ' ', $new_render);
                    $processed[$i] = ['html' => $new_render];
                }
                // Assign the processed renderings directly to the "renderings" key.
                $items[$index]["renderings"] = $processed;
                $items[$index]["uuid"] = $uuid;
                $items[$index]["link"] = $item['links']['link'];
                $items[$index]["description"] = $item['descriptions']['description']['value']['text'];
                if (isset($settings['linkToPortal']) && $settings['linkToPortal'] == 1) {
                    $items[$index]["portaluri"] = $item['info']['portalUrl'];
                }
            }
            $view["items"] = $items;
        }
        $offset = (((int)$currentPageNumber - 1) * (int)$settings['pageSize']);
        $view['offset'] = $offset;
        return $view;
    }
}
