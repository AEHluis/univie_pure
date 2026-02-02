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
        $xml = $this->buildProjectsQuery($settings, $currentPageNumber);

        $view = $this->webservice->getXml('projects', $xml);
        if (!$view) {
            return [
                'error' => 'SERVER_NOT_AVAILABLE',
                'message' => LocalizationUtility::translate('error.server_unavailable', 'univie_pure')
            ];
        }

        if (!is_array($view)) {
            return [
                'error' => 'SERVER_NOT_AVAILABLE',
                'message' => LocalizationUtility::translate('error.server_unavailable', 'univie_pure')
            ];
        }

        $totalCount = (int)$this->getArrayValue($view, 'count', 0);
        $items = $this->collectProjectItems($view, $settings);

        $pageSize = (int)$settings['pageSize'];
        if ($pageSize > 0 && count($items) < $pageSize && $totalCount > $this->calculateOffset($pageSize, $currentPageNumber) + $pageSize) {
            $nextPage = $currentPageNumber + 1;
            $nextOffset = $this->calculateOffset($pageSize, $nextPage);
            while (count($items) < $pageSize && $totalCount > $nextOffset) {
                $nextXml = $this->buildProjectsQuery($settings, $nextPage);
                $nextView = $this->webservice->getXml('projects', $nextXml);
                if (!is_array($nextView)) {
                    break;
                }
                $moreItems = $this->collectProjectItems($nextView, $settings);
                if (!empty($moreItems)) {
                    $items = array_merge($items, $moreItems);
                }
                $nextPage++;
                $nextOffset = $this->calculateOffset($pageSize, $nextPage);
            }
            $items = array_slice($items, 0, $pageSize);
        }

        $view['items'] = $items;
        $view['offset'] = $this->calculateOffset((int)$settings['pageSize'], (int)$currentPageNumber);
        return $view;

    }

    private function buildProjectsQuery(array $settings, int $currentPageNumber): string
    {
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
                    <field>visibility.*</field>
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
        return $xml;
    }

    private function collectProjectItems(array $view, array $settings): array
    {
        $projectItems = $this->getNestedArrayValue($view, 'items.project', null);
        if ($projectItems === null) {
            return [];
        }

        if (isset($projectItems['@attributes'])) {
            $projectItems = [$projectItems];
        }

        $isInCampus = class_exists(\T3luh\T3luhlib\PhpUtility::class)
            ? \T3luh\T3luhlib\PhpUtility::user_checkIP()
            : false;

        $items = [];
        foreach ($projectItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $processed = $this->processProjectItem($item, $settings, $isInCampus);
            if ($processed !== null) {
                $items[] = $processed;
            }
        }

        return $items;
    }

    private function processProjectItem(array $item, array $settings, bool $isInCampus): ?array
    {
        $visibilityKey = $this->getVisibilityKey($item);
        if (!$this->isVisibleForCurrentUser($visibilityKey, $isInCampus)) {
            return null;
        }

        $processed = [];
        $uuid = $this->getNestedArrayValue($item, '@attributes.uuid', '');
        $rendering = $this->getNestedArrayValue($item, 'renderings.rendering', '');
        $new_render = '';
        if (!empty($rendering)) {
            $new_render = $this->transformRenderingHtml($rendering, []);
        }
        $processed['renderings']['rendering']['html'] = $new_render;
        $processed['uuid'] = $uuid;

        if (isset($item['links']['link'])) {
            if (isset($item['links']['link'][0])) {
                $processed['links'] = $item['links']['link'];
            } else {
                $processed['links'] = [$item['links']['link']];
            }
        }

        $processed['description'] = $this->getNestedArrayValue($item, 'descriptions.description.value.text', '');

        if ((array_key_exists('linkToPortal', $settings)) && ($settings['linkToPortal'] == 1)) {
            $processed['portaluri'] = $this->getNestedArrayValue($item, 'info.portalUrl', '');
        }

        return $processed;
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
