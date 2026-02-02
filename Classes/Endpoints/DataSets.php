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

        $xml = $this->buildDataSetsQuery($settings, $currentPageNumber);
        $view = $this->webservice->getXml('datasets', $xml);

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
        $items = $this->collectDataSetItems($view, $settings);

        $pageSize = (int)$settings['pageSize'];
        if ($pageSize > 0 && count($items) < $pageSize && $totalCount > $this->calculateOffset($pageSize, $currentPageNumber) + $pageSize) {
            $nextPage = $currentPageNumber + 1;
            $nextOffset = $this->calculateOffset($pageSize, $nextPage);
            while (count($items) < $pageSize && $totalCount > $nextOffset) {
                $nextXml = $this->buildDataSetsQuery($settings, $nextPage);
                $nextView = $this->webservice->getXml('datasets', $nextXml);
                if (!is_array($nextView)) {
                    break;
                }
                $moreItems = $this->collectDataSetItems($nextView, $settings);
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

    private function buildDataSetsQuery(array $settings, int $currentPageNumber): string
    {
        $xml = '<?xml version="1.0"?><dataSetsQuery>';
        //set page size:
        $xml .= CommonUtilities::getProjectsForDatasetsXml($settings);
        //set page size:
        $xml .= CommonUtilities::getPageSize($settings['pageSize']);
        //set offset:
        $xml .= CommonUtilities::getOffset($settings['pageSize'], $currentPageNumber);

        $xml .= LanguageUtility::getLocale('xml');
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
        return $xml;
    }

    private function collectDataSetItems(array $view, array $settings): array
    {
        $dataSetItems = $this->getNestedArrayValue($view, 'items.dataSet', null);
        if ($dataSetItems === null) {
            return [];
        }

        if (isset($dataSetItems['@attributes'])) {
            $dataSetItems = [$dataSetItems];
        }

        $isInCampus = class_exists(\T3luh\T3luhlib\PhpUtility::class)
            ? \T3luh\T3luhlib\PhpUtility::user_checkIP()
            : false;

        $items = [];
        foreach ($dataSetItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $processed = $this->processDataSetItem($item, $settings, $isInCampus);
            if ($processed !== null) {
                $items[] = $processed;
            }
        }

        return $items;
    }

    private function processDataSetItem(array $item, array $settings, bool $isInCampus): ?array
    {
        $visibilityKey = $this->getVisibilityKey($item);
        if (!$this->isVisibleForCurrentUser($visibilityKey, $isInCampus)) {
            return null;
        }

        $processed = [];
        $uuid = $this->getNestedArrayValue($item, '@attributes.uuid', '');
        $rendering = $this->getNestedArrayValue($item, 'renderings.rendering', '');
        $new_render = is_array($rendering) ? implode(" ", $rendering) : $rendering;

        $new_render = $this->transformRenderingHtml(
            mb_convert_encoding($new_render, "UTF-8"),
            ['removeTypeParagraph' => true]
        );

        $processed['renderings']['rendering']['html'] = $new_render;
        $processed['uuid'] = $uuid;
        $processed['link'] = $this->getNestedArrayValue($item, 'links.link', []);
        $processed['description'] = $this->getNestedArrayValue($item, 'descriptions.description.value.text', '');

        return $processed;
    }
}
