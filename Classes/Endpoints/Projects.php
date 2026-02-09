<?php

namespace Univie\UniviePure\Endpoints;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Utility\CommonUtilities;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
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

        if ((int)$this->getArrayValue($settings, 'chooseSelector', -1) === 4) {
            return $this->getProjectsListByEquipmentSelection($settings, (int)$currentPageNumber);
        }

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

    private function getProjectsListByEquipmentSelection(array $settings, int $currentPageNumber): array
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $locale = LanguageUtility::getLocale(null);
        $logger->error('Equipment selector project list start', [
            'currentPageNumber' => $currentPageNumber,
            'settings' => $settings,
            'locale' => $locale,
        ]);
        CommonUtilities::debugLog('Equipment selector project list start', [
            'currentPageNumber' => $currentPageNumber,
            'settings' => $settings,
            'locale' => $locale,
        ]);

        $projectUuids = CommonUtilities::getRelatedEntityUuidsForEquipmentSelection($settings, 'relatedProjects');
        $projectUuids = array_values(array_unique(array_filter($projectUuids)));

        $pageSize = max(1, (int)$this->getArrayValue($settings, 'pageSize', 20));
        $offset = $this->calculateOffset($pageSize, $currentPageNumber);
        $pageUuids = array_slice($projectUuids, $offset, $pageSize);
        $projectTitlesByUuid = CommonUtilities::getRelatedProjectTitlesForEquipmentSelection($settings, $locale);
        $logger->error('Equipment selector project UUIDs resolved', [
            'totalUuids' => count($projectUuids),
            'offset' => $offset,
            'pageSize' => $pageSize,
            'pageUuids' => $pageUuids,
            'projectTitlesByUuid' => $projectTitlesByUuid,
        ]);
        CommonUtilities::debugLog('Equipment selector project UUIDs resolved', [
            'totalUuids' => count($projectUuids),
            'offset' => $offset,
            'pageSize' => $pageSize,
            'pageUuids' => $pageUuids,
            'projectTitlesByUuid' => $projectTitlesByUuid,
        ]);

        $items = [];
        $isInCampus = class_exists(\T3luh\T3luhlib\PhpUtility::class)
            ? \T3luh\T3luhlib\PhpUtility::user_checkIP()
            : false;
        $logger->error('Equipment selector runtime context', [
            'isInCampus' => $isInCampus,
        ]);
        CommonUtilities::debugLog('Equipment selector runtime context', [
            'isInCampus' => $isInCampus,
        ]);

        foreach ($pageUuids as $uuid) {
            $project = $this->webservice->getSingleResponse('projects', $uuid, 'json', true, 'short', $locale);
            $logger->error('Equipment selector project fetch result', [
                'uuid' => $uuid,
                'responseType' => gettype($project),
                'response' => $project,
            ]);
            CommonUtilities::debugLog('Equipment selector project fetch result', [
                'uuid' => $uuid,
                'responseType' => gettype($project),
                'response' => $project,
            ]);
            if (!is_array($project)) {
                $logger->error('Equipment selector project fetch returned non-array', [
                    'uuid' => $uuid
                ]);
                CommonUtilities::debugLog('Equipment selector project fetch returned non-array', [
                    'uuid' => $uuid
                ]);
                if (isset($projectTitlesByUuid[$uuid])) {
                    $items[] = [
                        'renderings' => [
                            'rendering' => [
                                'html' => '<h4 class="title">' . htmlspecialchars($projectTitlesByUuid[$uuid], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h4>'
                            ]
                        ],
                        'uuid' => $uuid
                    ];
                }
                continue;
            }

            if (isset($project['project']) && is_array($project['project'])) {
                $project = $project['project'];
            }

            $logger->error('Equipment selector project payload keys', [
                'uuid' => $uuid,
                'keys' => array_keys($project),
                'hasRendering' => isset($project['rendering']) || isset($project['renderings']),
                'hasVisibility' => isset($project['visibility']),
            ]);
            CommonUtilities::debugLog('Equipment selector project payload keys', [
                'uuid' => $uuid,
                'keys' => array_keys($project),
                'hasRendering' => isset($project['rendering']) || isset($project['renderings']),
                'hasVisibility' => isset($project['visibility']),
            ]);

            if (!isset($project['@attributes']['uuid']) && isset($project['uuid'])) {
                $project['@attributes']['uuid'] = $project['uuid'];
            }
            if (!isset($project['title']) && isset($projectTitlesByUuid[$uuid])) {
                $project['title'] = [
                    'text' => [
                        [
                            'locale' => $locale,
                            'value' => $projectTitlesByUuid[$uuid]
                        ]
                    ]
                ];
            }

            $processed = $this->processProjectItem($project, $settings, $isInCampus);
            if ($processed !== null) {
                $logger->error('Equipment selector project processed', [
                    'uuid' => $uuid,
                    'processed' => $processed,
                ]);
                CommonUtilities::debugLog('Equipment selector project processed', [
                    'uuid' => $uuid,
                    'processed' => $processed,
                ]);
                $items[] = $processed;
            } else {
                $logger->error('Equipment selector project dropped during processing', [
                    'uuid' => $uuid,
                    'project' => $project,
                ]);
                CommonUtilities::debugLog('Equipment selector project dropped during processing', [
                    'uuid' => $uuid,
                    'project' => $project,
                ]);
            }
        }

        $logger->error('Equipment selector project items built', [
            'itemsCount' => count($items),
            'totalUuids' => count($projectUuids),
        ]);
        CommonUtilities::debugLog('Equipment selector project items built', [
            'itemsCount' => count($items),
            'totalUuids' => count($projectUuids),
        ]);

        return [
            'count' => count($projectUuids),
            'items' => $items,
            'offset' => $offset
        ];
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
        // Add equipment based filter XML if available
        $equipmentSearchXml = CommonUtilities::getProjectsForEquipmentsXml($settings);
        $xml .= $equipmentSearchXml;

        //search AND filter:
        $isEquipmentSelector = (int)$this->getArrayValue($settings, 'chooseSelector', -1) === 4;
        if (($this->getArrayValue($settings, 'narrowBySearch') || $this->getArrayValue($settings, 'filter'))
            && !$isEquipmentSelector) {
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
        if ($uuid === '' && isset($item['uuid']) && is_string($item['uuid'])) {
            $uuid = $item['uuid'];
        }

        $rendering = $this->extractProjectRendering($item);
        if ($rendering === '') {
            $rendering = $this->buildFallbackProjectRendering($item);
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error('Project fallback rendering used', [
                'uuid' => $uuid,
                'itemKeys' => array_keys($item),
                'item' => $item,
                'fallbackRendering' => $rendering,
            ]);
            CommonUtilities::debugLog('Project fallback rendering used', [
                'uuid' => $uuid,
                'itemKeys' => array_keys($item),
                'item' => $item,
                'fallbackRendering' => $rendering,
            ]);
        }
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

    private function extractProjectRendering(array $item): string
    {
        $rendering = $this->getNestedArrayValue($item, 'renderings.rendering', '');
        if (is_string($rendering) && $rendering !== '') {
            return $rendering;
        }
        if (is_array($rendering)) {
            $candidate = (string)($rendering['value'] ?? $rendering['html'] ?? '');
            if ($candidate !== '') {
                return $candidate;
            }
        }

        $renderings = $this->getArrayValue($item, 'renderings', null);
        if (is_array($renderings)) {
            if (isset($renderings['rendering'])) {
                $candidate = $renderings['rendering'];
                if (is_string($candidate) && $candidate !== '') {
                    return $candidate;
                }
                if (is_array($candidate)) {
                    $candidate = (string)($candidate['html'] ?? $candidate['value'] ?? '');
                    if ($candidate !== '') {
                        return $candidate;
                    }
                }
            }

            foreach ($renderings as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                if (($entry['error'] ?? false) === true) {
                    continue;
                }
                $candidate = (string)($entry['html'] ?? $entry['value'] ?? '');
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        $candidate = $this->getArrayValue($item, 'rendering', '');
        return is_string($candidate) ? $candidate : '';
    }

    private function buildFallbackProjectRendering(array $item): string
    {
        $locale = LanguageUtility::getLocale(null);
        $title = $this->extractLocalizedField($this->getArrayValue($item, 'title', []), $locale);
        if ($title === '') {
            $title = $this->extractLocalizedField($this->getArrayValue($item, 'name', []), $locale);
        }

        if ($title === '' && isset($item['title']) && is_string($item['title'])) {
            $title = $item['title'];
        }

        if ($title === '') {
            $title = (string)$this->getArrayValue($item, 'name', 'Project');
        }

        return '<h4 class="title">' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h4>';
    }

    private function extractLocalizedField($fieldData, string $locale): string
    {
        if (is_string($fieldData) && trim($fieldData) !== '') {
            return $fieldData;
        }
        if (!is_array($fieldData)) {
            return '';
        }
        if (isset($fieldData['value']) && is_string($fieldData['value'])) {
            return $fieldData['value'];
        }
        if (!isset($fieldData['text'])) {
            return '';
        }

        $texts = $fieldData['text'];
        if (is_string($texts) && trim($texts) !== '') {
            return $texts;
        }

        // Handle associative locale map format: text[de_DE] = "..."
        if (is_array($texts) && isset($texts[$locale]) && is_string($texts[$locale])) {
            return $texts[$locale];
        }

        if (is_array($texts) && isset($texts['value']) && is_string($texts['value'])) {
            $texts = [$texts];
        }

        $fallback = '';
        if (is_array($texts)) {
            foreach ($texts as $textEntry) {
                if (!is_array($textEntry)) {
                    continue;
                }
                $value = isset($textEntry['value']) && is_string($textEntry['value']) ? $textEntry['value'] : '';
                if ($value === '') {
                    continue;
                }
                if ($fallback === '') {
                    $fallback = $value;
                }
                $entryLocale = isset($textEntry['locale']) && is_string($textEntry['locale']) ? $textEntry['locale'] : '';
                if ($entryLocale === $locale) {
                    return $value;
                }
                if ($entryLocale !== '' && strpos($locale, '_') !== false && strpos($entryLocale, substr($locale, 0, 2)) === 0) {
                    return $value;
                }
            }
        }

        return $fallback;
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
