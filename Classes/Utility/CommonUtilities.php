<?php

namespace Univie\UniviePure\Utility;

use Univie\UniviePure\Service\WebService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Context\Context;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/**
 * Helpers for all endpoints
 *
 */
class CommonUtilities
{
    public static function getArrayValue($array, $key, $default = null)
    {
        return self::arrayKeyExists($key, $array) ? $array[$key] : $default;
    }

    public static function arrayKeyExists($key, $array): bool
    {
        return is_array($array) && array_key_exists($key, $array);
    }

    public static function getNestedArrayValue($array, string $path, $default = null)
    {
        // Return default if input is not an array
        if (!is_array($array)) {
            return $default;
        }

        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            // Check if current is an array and if the key exists
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }


    public static function getPageSize($pageSize)
    {
        if ($pageSize == 0 || $pageSize === null) {
            $pageSize = 20;
        }
        return '<size>' . (int)$pageSize . '</size>';
    }

    /**
     * keep track of the counter
     * @return String xml
     */
    public static function getOffset($pageSize, $currentPage)
    {
        $offset = $currentPage;
        $offset = ($offset - 1 < 0) ? 0 : $offset - 1;
        return '<offset>' . (int)($offset * (int)$pageSize) . '</offset>';
    }


    /**
     * Either send a request for a unit or for persons
     * @return String xml
     */
    public static function getPersonsOrOrganisationsXml($settings)
    {
        $xml = "";
        // If settings isn't an array, return empty string
        if (!is_array($settings)) {
            return $xml;
        }

        // Get the chooseSelector value with a default of -1
        $chooseSelector = self::getArrayValue($settings, 'chooseSelector', -1);

        // Based on chooseSelector value, generate appropriate XML
        switch ($chooseSelector) {
            case 0:
                // Resarch-output for organisations:
                $xml = self::getOrganisationsXml($settings);
                break;
            case 1:
                // Research-output for persons:
                $xml = self::getPersonsXml($settings);
                break;
            // Default case returns empty string
        }

        return $xml;
    }


    /**
     * Organisations query
     * @return String xml
     */
    public static function getOrganisationsXml($settings)
    {
// Safely read relevant keys:
        $selectorOrganisations = self::getArrayValue($settings, 'selectorOrganisations', '');
        $narrowBySearch = self::getArrayValue($settings, 'narrowBySearch', '');

        if ($selectorOrganisations === '' && $narrowBySearch !== '') {
            return '';
        }

        $xml = '<forOrganisationalUnits><uuids>';
        $organisations = explode(',', $selectorOrganisations);
        foreach ((array)$organisations as $org) {
            if (strpos($org, '|') !== false) {
                $org = explode('|', $org)[0];
            }
            $sanitizedOrgUuid = htmlspecialchars($org, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $xml .= '<uuid>' . $sanitizedOrgUuid . '</uuid>';
            // check for sub units
            if (self::getArrayValue($settings, 'includeSubUnits', 0) == 1) {
                $subUnits = self::getSubUnits($org);
                if (is_array($subUnits) && count($subUnits) > 1) {
                    foreach ($subUnits as $subUnit) {
                        if (self::getArrayValue($subUnit, 'uuid') !== $org) {
                            $sanitizedSubUnitUuid = htmlspecialchars($subUnit['uuid'], ENT_QUOTES | ENT_XML1, 'UTF-8');
                            $xml .= '<uuid>' . $sanitizedSubUnitUuid . '</uuid>';
                        }
                    }
                }
            }
        }
        $xml .= '</uuids><hierarchyDepth>100</hierarchyDepth></forOrganisationalUnits>';
        return $xml;
    }

    /**
     * Persons query
     * @return String xml
     */
    public static function getPersonsXml($settings)
    {
        $selectorPersons = self::getArrayValue($settings, 'selectorPersons', '');
        $narrowBySearch = self::getArrayValue($settings, 'narrowBySearch', '');

        if ($selectorPersons === '' && $narrowBySearch !== '') {
            return '';
        }

        $xml = '<forPersons><uuids>';
        $persons = explode(',', $selectorPersons);
        foreach ((array)$persons as $person) {
            if (strpos($person, '|') !== false) {
                $person = explode('|', $person)[0];
            }
            $sanitizedPersonUuid = htmlspecialchars($person, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $xml .= '<uuid>' . $sanitizedPersonUuid . '</uuid>';
        }
        $xml .= '</uuids></forPersons>';
        return $xml;
    }

    /**
     * Projects query
     * @return String xml | boolean
     */
    public static function getProjectsXml(array $settings)
    {
        // Safely retrieve settings
        $selectorProjects = self::getArrayValue($settings, 'selectorProjects', '');
        $narrowBySearch = self::getArrayValue($settings, 'narrowBySearch', '');
        $chooseSelector = self::getArrayValue($settings, 'chooseSelector', -1);

        // 1) If no selectorProjects but user did enter a search, return empty string
        if ($selectorProjects === '' && $narrowBySearch !== '') {
            return '';
        }

        // 2) If not choosing "2", then we do nothing special here
        if ($chooseSelector !== 2) {
            return false;
        }

// Build the request XML for the selected projects
        $xmlProjects = '<?xml version="1.0"?><projectsQuery><uuids>';
        $projects = explode(',', $selectorProjects);
        foreach ($projects as $proj) {
            // If user appended "|something", strip that off
            if (strpos($proj, '|') !== false) {
                $proj = explode('|', $proj)[0];
            }
            if (!empty($proj)) {
                $sanitizedProjUuid = htmlspecialchars($proj, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $xmlProjects .= '<uuid>' . $sanitizedProjUuid . '</uuid>';
            }
        }
        $xmlProjects .= '</uuids>
    <size>99999</size>
    <linkingStrategy>string</linkingStrategy>
    <locales><locale>de_DE</locale></locales>
    <fields><field>relatedResearchOutputs.uuid</field></fields>
    <orderings><ordering>title</ordering></orderings>
</projectsQuery>';

        // 4) Call the webservice
        $webservice = new WebService;
        $publications = $webservice->getJson('projects', $xmlProjects);

        // 5) Build the final return XML with related research outputs
        $xml = '';
        if (is_array($publications) && isset($publications['items'])) {
            $hasResearchOutputs = false;
            $xmlTemp = "";
            foreach ($publications['items'] as $researchOutputs) {
                if (!empty($researchOutputs) && isset($researchOutputs['relatedResearchOutputs'])) {
                    foreach ($researchOutputs['relatedResearchOutputs'] as $researchOutput) {
                        $sanitizedResearchUuid = htmlspecialchars($researchOutput['uuid'], ENT_QUOTES | ENT_XML1, 'UTF-8');
                        $xmlTemp .= '<uuid>' . $sanitizedResearchUuid . '</uuid>';
                        $hasResearchOutputs = true;
                    }
                }
            }

            if ($hasResearchOutputs) {
                $xml .= "<uuids>" . $xmlTemp . "</uuids>";
            } else {
                // Force no results by providing a non-existent UUID when no research outputs exist
                $xml .= "<uuids><uuid>NO_RESEARCH_OUTPUTS_FOUND</uuid></uuids>";
            }
        }
        return $xml;
    }

    /**
     * Projects query
     * @return String xml | boolean
     */
    public static function getProjectsForDatasetsXml($settings)
    {
        // Ensure $settings is an array
        if (!is_array($settings)) {
            $settings = [];
        }

        // Safely retrieve keys
        $narrowBySearch = self::getArrayValue($settings, 'narrowBySearch', '');
        $chooseSelector = self::getArrayValue($settings, 'chooseSelector', null);
        $selectorProjects = self::getArrayValue($settings, 'selectorProjects', '');

        // If no projects were set but search is given:
        if ($selectorProjects === '' && $narrowBySearch !== '') {
            return '';
        }

        if ($chooseSelector === 2) {
            // Build Projects query
            $xmlProjects = '<?xml version="1.0"?><projectsQuery><uuids>';
            $projectsArray = explode(',', $selectorProjects);

            foreach ($projectsArray as $project) {
                if (strpos($project, "|") !== false) {
                    $project = explode("|", $project)[0];
                }
                if (!empty($project)) {
                    $sanitizedProjectUuid = htmlspecialchars($project, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    $xmlProjects .= '<uuid>' . $sanitizedProjectUuid . '</uuid>';
                }
            }

            $xmlProjects .= '</uuids>
    <size>99999</size>
    <linkingStrategy>string</linkingStrategy>
    <locales><locale>de_DE</locale></locales>
    <fields><field>relatedDataSets.uuid</field></fields>
    <orderings><ordering>title</ordering></orderings>
</projectsQuery>';

            $webservice = new WebService;
            $datasets = $webservice->getJson('projects', $xmlProjects);

            // Build final XML with the relatedDataSets
            $xml = "";
            if (is_array($datasets) && isset($datasets['items'])) {
                $hasDatasets = false;
                $xmlTemp = "";
                foreach ($datasets['items'] as $d) {
                    if (!empty($d) && isset($d['relatedDataSets'])) {
                        foreach ($d['relatedDataSets'] as $i) {
                            $sanitizedDatasetUuid = htmlspecialchars($i['uuid'], ENT_QUOTES | ENT_XML1, 'UTF-8');
                            $xmlTemp .= '<uuid>' . $sanitizedDatasetUuid . '</uuid>';
                            $hasDatasets = true;
                        }
                    }
                }

                if ($hasDatasets) {
                    $xml .= "<uuids>" . $xmlTemp . "</uuids>";
                } else {
                    // Force no results by providing a non-existent UUID when no datasets exist
                    $xml .= "<uuids><uuid>NO_DATASETS_FOUND</uuid></uuids>";
                }
            }
            return $xml;
        }

        // Default (not chooseSelector = 2)
        return false;
    }

    /**
     * query sub organisations for a unit
     * @return array subUnits Array of all Units connected
     */
    public static function getSubUnits($orgId)
    {
        $orgName = self::getNameForUuid($orgId);
        $xml = '<?xml version="1.0"?>
<organisationalUnitsQuery>
    <size>300</size>
    <fields><field>uuid</field></fields>
    <orderings><ordering>type</ordering></orderings>
    <returnUsedContent>true</returnUsedContent>
    <navigationLink>true</navigationLink>
    <searchString>"' . htmlspecialchars($orgName, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"</searchString>
</organisationalUnitsQuery>';
        $webservice = new WebService;
        $subUnits = $webservice->getJson('organisational-units', $xml);

// Safely verify structure before returning
        if (is_array($subUnits) && isset($subUnits['count'])
            && $subUnits['count'] > 1
            && isset($subUnits['items'])
        ) {
            return $subUnits['items'];
        }
        return [];
    }

    /*
     * query name by uuid
     * @return string name
     */
    public static function getNameForUuid($orgId)
    {
        $xml = '<?xml version="1.0"?>
<organisationalUnitsQuery>
    <uuids><uuid>' . htmlspecialchars($orgId, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</uuid></uuids>
    <size>1</size>
    <offset>0</offset>
    <locales><locale>de_DE</locale></locales>
    <fields><field>name.text.value</field></fields>
</organisationalUnitsQuery>';
        $webservice = new WebService;
        $orgName = $webservice->getJson('organisational-units', $xml);

        if (is_array($orgName) && ($orgName['count'] ?? 0) === 1) {
            $items = $orgName['items'] ?? [];
            if (isset($items[0]['name']['text'][0]['value'])) {
                return $items[0]['name']['text'][0]['value'];
            }
        }
        return '';
    }
}