<?php

namespace Univie\UniviePure\Utility;

use Univie\UniviePure\Service\WebService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Core\Environment;

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
    protected static function getLogger()
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    public static function debugLog(string $message, array $context = []): void
    {
        $line = '[univie_pure] ' . $message;
        if (!empty($context)) {
            $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $line .= ' | ' . ($json !== false ? $json : 'context_encode_failed');
        }

        error_log($line);

        try {
            $logDir = Environment::getVarPath() . '/log';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            @file_put_contents($logDir . '/univie_pure_debug.log', date('c') . ' ' . $line . PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            // Keep runtime unaffected if file logging fails.
        }
    }

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
        $chooseSelector = (int)self::getArrayValue($settings, 'chooseSelector', -1);

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
            case 3:
                // Research-output for persons with organization:
                $xml = self::getPersonsWithOrganizationXml($settings);
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
     * Persons with organization query
     * @return String xml
     */
    public static function getPersonsWithOrganizationXml($settings)
    {
        $selectorPersonsWithOrganization = self::getArrayValue($settings, 'selectorPersonsWithOrganization', '');
        $narrowBySearch = self::getArrayValue($settings, 'narrowBySearch', '');

        if ($selectorPersonsWithOrganization === '' && $narrowBySearch !== '') {
            return '';
        }

        $xml = '<forPersons><uuids>';
        $persons = explode(',', $selectorPersonsWithOrganization);
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
        $chooseSelector = (int)self::getArrayValue($settings, 'chooseSelector', -1);

        // 1) If no selectorProjects but user did enter a search, return empty string
        if ($selectorProjects === '' && $narrowBySearch !== '') {
            return '';
        }

        // 2) If not choosing "2", then we do nothing special here
        if ($chooseSelector !== 2) {
            return false;
        }

        $projectTerms = [];
        $projects = explode(',', $selectorProjects);
        foreach ($projects as $proj) {
            // If user appended "|something", strip that off
            if (strpos($proj, '|') !== false) {
                $proj = explode('|', $proj)[0];
            }
            if (!empty($proj)) {
                $projectTerms[] = '"' . $proj . '"';
            }
        }

        if (empty($projectTerms)) {
            return '<searchString>__NO_RESEARCH_OUTPUTS_FOUND__</searchString>';
        }

// Build the request XML for the selected projects
        $xmlProjects = '<?xml version="1.0"?><projectsQuery>
    <size>99999</size>
    <linkingStrategy>string</linkingStrategy>
    <locales><locale>de_DE</locale></locales>
    <fields><field>relatedResearchOutputs.uuid</field></fields>
    <orderings><ordering>title</ordering></orderings>
    <searchString>' . htmlspecialchars(implode(' OR ', $projectTerms), ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>
</projectsQuery>';

        // 4) Call the webservice
        $webservice = GeneralUtility::makeInstance(WebService::class);
        $publications = $webservice->getJson('projects', $xmlProjects);

        // 5) Build the final return XML with related research outputs
        $relatedResearchOutputUuids = [];
        if (is_array($publications) && isset($publications['items'])) {
            foreach ($publications['items'] as $researchOutputs) {
                if (!empty($researchOutputs) && isset($researchOutputs['relatedResearchOutputs'])) {
                    foreach ($researchOutputs['relatedResearchOutputs'] as $researchOutput) {
                        $uuid = (string)($researchOutput['uuid'] ?? '');
                        if ($uuid !== '') {
                            $relatedResearchOutputUuids[$uuid] = true;
                        }
                    }
                }
            }
        }

        $filter = self::getArrayValue($settings, 'filter', '');
        $userTerms = trim($narrowBySearch . ' ' . $filter);

        return self::buildResearchOutputSearchStringFragment(array_keys($relatedResearchOutputUuids), $userTerms);
    }

    /**
     * Resolve research output UUIDs through selected equipments
     * and return them as query fragment for research-outputs queries.
     */
    public static function getResearchOutputsForEquipmentsXml(array $settings)
    {
        $selectorEquipments = self::getArrayValue($settings, 'selectorEquipments', '');
        $narrowBySearch = self::getArrayValue($settings, 'narrowBySearch', '');
        $filter = self::getArrayValue($settings, 'filter', '');
        $chooseSelector = (int)self::getArrayValue($settings, 'chooseSelector', -1);

        if ($selectorEquipments === '' && $narrowBySearch !== '') {
            return '';
        }

        if ($chooseSelector !== 4) {
            return false;
        }

        $userTerms = trim($narrowBySearch . ' ' . $filter);

        if ($selectorEquipments === '') {
            $xml = self::buildResearchOutputSearchStringFragment([], $userTerms);
            self::getLogger()->error('Equipment research-output filter fragment built (no selector values)', [
                'fragment' => $xml
            ]);
            self::debugLog('Equipment research-output filter fragment built (no selector values)', [
                'fragment' => $xml
            ]);
            return $xml;
        }

        $uuids = self::getRelatedUuidsForEquipments($selectorEquipments, 'relatedResearchOutputs');
        if (empty($uuids)) {
            $projectUuids = self::getRelatedUuidsForEquipments($selectorEquipments, 'relatedProjects');
            if (!empty($projectUuids)) {
                $uuids = self::getRelatedResearchOutputUuidsForProjectUuids($projectUuids);
                self::getLogger()->error('Equipment research-output UUID fallback via related projects', [
                    'projectUuidsCount' => count($projectUuids),
                    'researchOutputUuidsCount' => count($uuids),
                    'projectUuids' => $projectUuids,
                    'researchOutputUuids' => $uuids,
                ]);
                self::debugLog('Equipment research-output UUID fallback via related projects', [
                    'projectUuidsCount' => count($projectUuids),
                    'researchOutputUuidsCount' => count($uuids),
                    'projectUuids' => $projectUuids,
                    'researchOutputUuids' => $uuids,
                ]);
            }
        }
        if (empty($uuids)) {
            $xml = self::buildResearchOutputSearchStringFragment([], $userTerms);
            self::getLogger()->error('Equipment research-output filter fragment built (no related UUIDs)', [
                'fragment' => $xml
            ]);
            self::debugLog('Equipment research-output filter fragment built (no related UUIDs)', [
                'fragment' => $xml
            ]);
            return $xml;
        }

        $xml = self::buildResearchOutputSearchStringFragment($uuids, $userTerms);

        self::getLogger()->error('Equipment research-output filter fragment built', [
            'uuidsCount' => count($uuids),
            'uuids' => $uuids,
            'hasUserTerms' => $userTerms !== '',
            'fragment' => $xml,
        ]);
        self::debugLog('Equipment research-output filter fragment built', [
            'uuidsCount' => count($uuids),
            'uuids' => $uuids,
            'hasUserTerms' => $userTerms !== '',
            'fragment' => $xml,
        ]);

        return $xml;
    }

    /**
     * Build a research output searchString fragment.
     * researchOutputsQuery schema does not accept <uuids>.
     */
    protected static function buildResearchOutputSearchStringFragment(array $uuids, string $userTerms): string
    {
        $parts = [];
        foreach ($uuids as $uuid) {
            $uuid = trim((string)$uuid);
            if ($uuid !== '') {
                $parts[] = '"' . $uuid . '"';
            }
        }

        $search = empty($parts) ? '__NO_RESEARCH_OUTPUTS_FOUND__' : implode(' OR ', $parts);
        $userTerms = trim($userTerms);
        if ($userTerms !== '') {
            $search .= ' ' . $userTerms;
        }

        return '<searchString>' . htmlspecialchars($search, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>';
    }

    /**
     * Resolve related research-output UUIDs by querying selected project UUIDs.
     */
    protected static function getRelatedResearchOutputUuidsForProjectUuids(array $projectUuids): array
    {
        $webservice = GeneralUtility::makeInstance(WebService::class);
        $logger = self::getLogger();
        $locale = LanguageUtility::getLocale(null);
        $uuids = [];

        foreach ($projectUuids as $projectUuidRaw) {
            $projectUuid = trim((string)$projectUuidRaw);
            if ($projectUuid === '') {
                continue;
            }

            $project = $webservice->getSingleResponse('projects', $projectUuid, 'json', true, null, $locale);
            if (!is_array($project)) {
                $logger->error('Project->research-output fallback: project fetch failed', [
                    'projectUuid' => $projectUuid,
                    'responseType' => gettype($project),
                ]);
                self::debugLog('Project->research-output fallback: project fetch failed', [
                    'projectUuid' => $projectUuid,
                    'responseType' => gettype($project),
                ]);
                continue;
            }

            if (isset($project['project']) && is_array($project['project'])) {
                $project = $project['project'];
            }

            $relationNodes = [];
            if (isset($project['relatedResearchOutputs']) && is_array($project['relatedResearchOutputs'])) {
                $relationNodes[] = $project['relatedResearchOutputs'];
            }

            $foundNodes = self::findRelationNodesByKey($project, ['relatedResearchOutputs', 'relatedResearchOutput']);
            if (!empty($foundNodes)) {
                $relationNodes = array_merge($relationNodes, $foundNodes);
            }

            $projectResolved = [];
            foreach ($relationNodes as $node) {
                foreach (self::extractRelatedEntityUuids($node, 'relatedResearchOutputs') as $uuid) {
                    $uuids[$uuid] = true;
                    $projectResolved[$uuid] = true;
                }
            }

            $logger->error('Project->research-output fallback: extracted UUIDs for project', [
                'projectUuid' => $projectUuid,
                'projectKeys' => array_keys($project),
                'resolvedUuids' => array_keys($projectResolved),
            ]);
            self::debugLog('Project->research-output fallback: extracted UUIDs for project', [
                'projectUuid' => $projectUuid,
                'projectKeys' => array_keys($project),
                'resolvedUuids' => array_keys($projectResolved),
            ]);
        }

        return array_keys($uuids);
    }

    /**
     * Resolve project UUIDs through selected equipments
     * and return them as <uuids>...</uuids> block for projects queries.
     */
    public static function getProjectsForEquipmentsXml(array $settings)
    {
        $selectorEquipments = self::getArrayValue($settings, 'selectorEquipments', '');
        $narrowBySearch = self::getArrayValue($settings, 'narrowBySearch', '');
        $filter = self::getArrayValue($settings, 'filter', '');
        $chooseSelector = (int)self::getArrayValue($settings, 'chooseSelector', -1);

        if ($selectorEquipments === '' && $narrowBySearch !== '') {
            return '';
        }

        if ($chooseSelector !== 4) {
            return false;
        }

        $userTerms = trim($narrowBySearch . ' ' . $filter);

        if ($selectorEquipments === '') {
            $noMatch = '__NO_PROJECTS_FOUND__';
            if ($userTerms !== '') {
                $noMatch .= ' ' . $userTerms;
            }
            return '<searchString>' . htmlspecialchars($noMatch, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>';
        }

        $uuids = self::getRelatedUuidsForEquipments($selectorEquipments, 'relatedProjects');
        if (empty($uuids)) {
            $noMatch = '__NO_PROJECTS_FOUND__';
            if ($userTerms !== '') {
                $noMatch .= ' ' . $userTerms;
            }
            return '<searchString>' . htmlspecialchars($noMatch, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>';
        }

        $parts = [];
        foreach ($uuids as $uuid) {
            $parts[] = '"' . $uuid . '"';
        }
        $uuidSearch = implode(' OR ', $parts);
        if ($userTerms !== '') {
            $uuidSearch .= ' ' . $userTerms;
        }

        return '<searchString>' . htmlspecialchars($uuidSearch, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>';
    }

    /**
     * Collect related entity UUIDs from selected equipments.
     */
    protected static function getRelatedUuidsForEquipments(string $selectorEquipments, string $relationField): array
    {
        $logger = self::getLogger();
        $locale = LanguageUtility::getLocale(null);
        $singularField = rtrim($relationField, 's');
        $equipmentUuids = [];

        $xmlEquipments = '<?xml version="1.0"?><equipmentsQuery><uuids>';
        $equipments = explode(',', $selectorEquipments);
        foreach ($equipments as $equipment) {
            if (strpos($equipment, '|') !== false) {
                $equipment = explode('|', $equipment)[0];
            }
            if (!empty($equipment)) {
                $sanitizedUuid = htmlspecialchars($equipment, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $xmlEquipments .= '<uuid>' . $sanitizedUuid . '</uuid>';
                $equipmentUuids[] = $equipment;
            }
        }

        $relationFieldXml = htmlspecialchars($relationField, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $singularFieldXml = htmlspecialchars($singularField, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $relationSpecificFieldsXml = self::buildRelationFieldSelectionXml($relationField);

        $xmlEquipments .= '</uuids>
    <size>99999</size>
    <linkingStrategy>string</linkingStrategy>
    <locales><locale>' . htmlspecialchars($locale, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</locale></locales>
    <fields>
        <field>' . $relationFieldXml . '.uuid</field>
        <field>' . $relationFieldXml . '.*</field>
        <field>' . $singularFieldXml . '.uuid</field>
        <field>' . $singularFieldXml . '.*</field>
        ' . $relationSpecificFieldsXml . '
    </fields>
</equipmentsQuery>';

        $logger->error('Equipment relation lookup request prepared', [
            'relationField' => $relationField,
            'locale' => $locale,
            'selectorEquipmentsRaw' => $selectorEquipments,
            'selectorEquipmentsParsed' => $equipmentUuids,
            'xml' => $xmlEquipments,
        ]);
        self::debugLog('Equipment relation lookup request prepared', [
            'relationField' => $relationField,
            'locale' => $locale,
            'selectorEquipmentsRaw' => $selectorEquipments,
            'selectorEquipmentsParsed' => $equipmentUuids,
            'xml' => $xmlEquipments,
        ]);

        $webservice = GeneralUtility::makeInstance(WebService::class);
        $response = $webservice->getJson('equipments', $xmlEquipments);
        $logger->error('Equipment relation lookup response received', [
            'relationField' => $relationField,
            'responseType' => gettype($response),
            'response' => $response,
        ]);
        self::debugLog('Equipment relation lookup response received', [
            'relationField' => $relationField,
            'responseType' => gettype($response),
            'response' => $response,
        ]);

        $uniqueUuids = [];
        $perEquipmentUuids = [];
        if (is_array($response)) {
            $items = $response['items'] ?? [];
            if (isset($items['equipment'])) {
                $items = $items['equipment'];
            }
            if (self::arrayKeyExists('uuid', $items) || self::arrayKeyExists('@attributes', $items)) {
                $items = [$items];
            }

            foreach ($items as $equipment) {
                if (!is_array($equipment)) {
                    continue;
                }
                $relationCandidates = [];
                if (isset($equipment[$relationField])) {
                    $relationCandidates[] = $equipment[$relationField];
                }
                $singularField = rtrim($relationField, 's');
                if ($singularField !== $relationField && isset($equipment[$singularField])) {
                    $relationCandidates[] = $equipment[$singularField];
                }
                if (empty($relationCandidates)) {
                    $relationCandidates = self::findRelationNodesByKey($equipment, [$relationField, $singularField]);
                }

                $equipmentUuid = (string)self::getArrayValue($equipment, 'uuid', self::getArrayValue($equipment, '@attributes.uuid', ''));
                $extractedForEquipment = [];
                foreach ($relationCandidates as $relationData) {
                    foreach (self::extractRelatedEntityUuids($relationData, $relationField) as $uuid) {
                        $uniqueUuids[$uuid] = true;
                        $extractedForEquipment[] = $uuid;
                    }
                }
                $perEquipmentUuids[] = [
                    'equipmentUuid' => $equipmentUuid,
                    'relationCandidatesCount' => count($relationCandidates),
                    'extractedUuids' => array_values(array_unique($extractedForEquipment))
                ];
            }
        }

        $logger->error('Equipment relation UUID extraction result', [
            'relationField' => $relationField,
            'perEquipment' => $perEquipmentUuids,
            'uniqueUuids' => array_keys($uniqueUuids),
            'uniqueCount' => count($uniqueUuids),
        ]);
        self::debugLog('Equipment relation UUID extraction result', [
            'relationField' => $relationField,
            'perEquipment' => $perEquipmentUuids,
            'uniqueUuids' => array_keys($uniqueUuids),
            'uniqueCount' => count($uniqueUuids),
        ]);

        if (empty($uniqueUuids)) {
            $sample = null;
            if (isset($items) && is_array($items) && !empty($items)) {
                $first = is_array($items[0] ?? null) ? $items[0] : null;
                if ($first !== null) {
                    $sample = [
                        'topLevelKeys' => array_keys($first),
                        'relationField' => $relationField,
                        'singularField' => $singularField
                    ];
                }
            }
            $logger->error('No related UUIDs extracted for equipment selector', [
                'relationField' => $relationField,
                'selectorEquipmentsCount' => count(array_filter(explode(',', $selectorEquipments))),
                'responseHasItems' => isset($items) && is_array($items),
                'sample' => $sample
            ]);
            self::debugLog('No related UUIDs extracted for equipment selector', [
                'relationField' => $relationField,
                'selectorEquipmentsCount' => count(array_filter(explode(',', $selectorEquipments))),
                'responseHasItems' => isset($items) && is_array($items),
                'sample' => $sample
            ]);
        }

        return array_keys($uniqueUuids);
    }

    /**
     * Public helper for equipment selector relations.
     * Returns UUIDs of related entities for selected equipments.
     */
    public static function getRelatedEntityUuidsForEquipmentSelection(array $settings, string $relationField): array
    {
        $chooseSelector = (int)self::getArrayValue($settings, 'chooseSelector', -1);
        if ($chooseSelector !== 4) {
            return [];
        }

        $selectorEquipments = self::getArrayValue($settings, 'selectorEquipments', '');
        if ($selectorEquipments === '') {
            return [];
        }

        return self::getRelatedUuidsForEquipments($selectorEquipments, $relationField);
    }

    /**
     * Resolve related project titles by project UUID from selected equipments.
     *
     * @return array<string,string> [projectUuid => localizedTitle]
     */
    public static function getRelatedProjectTitlesForEquipmentSelection(array $settings, ?string $locale = null): array
    {
        $logger = self::getLogger();
        $chooseSelector = (int)self::getArrayValue($settings, 'chooseSelector', -1);
        if ($chooseSelector !== 4) {
            return [];
        }

        $selectorEquipments = self::getArrayValue($settings, 'selectorEquipments', '');
        if ($selectorEquipments === '') {
            return [];
        }

        $locale = $locale ?: LanguageUtility::getLocale(null);
        $xmlEquipments = '<?xml version="1.0"?><equipmentsQuery><uuids>';
        $equipments = explode(',', $selectorEquipments);
        foreach ($equipments as $equipment) {
            if (strpos($equipment, '|') !== false) {
                $equipment = explode('|', $equipment)[0];
            }
            if (!empty($equipment)) {
                $xmlEquipments .= '<uuid>' . htmlspecialchars($equipment, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</uuid>';
            }
        }
        $xmlEquipments .= '</uuids>
    <size>99999</size>
    <linkingStrategy>string</linkingStrategy>
    <locales><locale>' . htmlspecialchars($locale, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</locale></locales>
    <fields>
        <field>relatedProjects.relatedProject.uuid</field>
        <field>relatedProjects.relatedProject.name.*</field>
        <field>relatedProjects.relatedProject.name.text</field>
        <field>relatedProjects.relatedProject.name.value</field>
    </fields>
</equipmentsQuery>';

        $logger->error('Equipment project title lookup request prepared', [
            'locale' => $locale,
            'selectorEquipmentsRaw' => $selectorEquipments,
            'xml' => $xmlEquipments,
        ]);
        self::debugLog('Equipment project title lookup request prepared', [
            'locale' => $locale,
            'selectorEquipmentsRaw' => $selectorEquipments,
            'xml' => $xmlEquipments,
        ]);

        $webservice = GeneralUtility::makeInstance(WebService::class);
        $response = $webservice->getJson('equipments', $xmlEquipments);
        $logger->error('Equipment project title lookup response received', [
            'responseType' => gettype($response),
            'response' => $response,
        ]);
        self::debugLog('Equipment project title lookup response received', [
            'responseType' => gettype($response),
            'response' => $response,
        ]);
        if (!is_array($response)) {
            return [];
        }

        $items = $response['items'] ?? [];
        if (isset($items['equipment'])) {
            $items = $items['equipment'];
        }
        if (self::arrayKeyExists('uuid', $items) || self::arrayKeyExists('@attributes', $items)) {
            $items = [$items];
        }

        $titlesByUuid = [];
        foreach ((array)$items as $equipment) {
            if (!is_array($equipment)) {
                continue;
            }

            $relatedProjectsContainer = $equipment['relatedProjects'] ?? null;
            if (!is_array($relatedProjectsContainer)) {
                continue;
            }

            $relatedProjects = $relatedProjectsContainer['relatedProject'] ?? $relatedProjectsContainer;
            if (self::arrayKeyExists('uuid', $relatedProjects) || self::arrayKeyExists('@attributes', $relatedProjects)) {
                $relatedProjects = [$relatedProjects];
            }

            foreach ((array)$relatedProjects as $relatedProject) {
                if (!is_array($relatedProject)) {
                    continue;
                }
                $uuid = self::getArrayValue($relatedProject, 'uuid', '');
                if ($uuid === '') {
                    $uuid = self::getArrayValue($relatedProject, '@attributes.uuid', '');
                }
                if ($uuid === '') {
                    continue;
                }

                $title = self::extractLocalizedText($relatedProject['name'] ?? null, $locale);
                if ($title !== '') {
                    $titlesByUuid[$uuid] = $title;
                }
            }
        }

        $logger->error('Equipment project titles resolved', [
            'titlesByUuidCount' => count($titlesByUuid),
            'titlesByUuid' => $titlesByUuid,
        ]);
        self::debugLog('Equipment project titles resolved', [
            'titlesByUuidCount' => count($titlesByUuid),
            'titlesByUuid' => $titlesByUuid,
        ]);

        return $titlesByUuid;
    }

    /**
     * Extract localized text from Pure "name"/"title" structures.
     */
    protected static function extractLocalizedText($fieldData, string $locale): string
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

        $texts = $fieldData['text'] ?? null;
        if (is_string($texts) && trim($texts) !== '') {
            return $texts;
        }
        if (!is_array($texts)) {
            return '';
        }

        if (isset($texts[$locale]) && is_string($texts[$locale])) {
            return $texts[$locale];
        }
        if (isset($texts['value']) && is_string($texts['value'])) {
            $texts = [$texts];
        }

        $fallback = '';
        foreach ($texts as $textEntry) {
            if (!is_array($textEntry)) {
                continue;
            }
            $value = (string)($textEntry['value'] ?? '');
            if ($value === '') {
                continue;
            }
            if ($fallback === '') {
                $fallback = $value;
            }
            $entryLocale = (string)($textEntry['locale'] ?? '');
            if ($entryLocale === $locale) {
                return $value;
            }
            if ($entryLocale !== '' && strpos($locale, '_') !== false && strpos($entryLocale, substr($locale, 0, 2)) === 0) {
                return $value;
            }
        }

        return $fallback;
    }

    /**
     * Recursively extract UUID fields from relation object payload.
     */
    protected static function extractUuidObjectsRecursively($data): array
    {
        $uuids = [];
        if (!is_array($data)) {
            return $uuids;
        }

        $uuid = self::getArrayValue($data, 'uuid', '');
        if ($uuid === '') {
            $uuid = self::getArrayValue($data, '@attributes.uuid', '');
        }
        if ($uuid !== '') {
            $uuids[] = $uuid;
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $uuids = array_merge($uuids, self::extractUuidObjectsRecursively($value));
            }
        }

        return array_values(array_unique($uuids));
    }

    /**
     * Recursively collect nodes by relation key name.
     */
    protected static function findRelationNodesByKey($data, array $targetKeys): array
    {
        $nodes = [];
        if (!is_array($data)) {
            return $nodes;
        }

        foreach ($data as $key => $value) {
            if (in_array((string)$key, $targetKeys, true) && is_array($value)) {
                $nodes[] = $value;
            }
            if (is_array($value)) {
                $nodes = array_merge($nodes, self::findRelationNodesByKey($value, $targetKeys));
            }
        }

        return $nodes;
    }

    /**
     * Add explicit relation field paths to match Pure's nested relation structures.
     */
    protected static function buildRelationFieldSelectionXml(string $relationField): string
    {
        $fields = [];
        if ($relationField === 'relatedProjects') {
            $fields = [
                'relatedProjects.relatedProject.uuid',
                'relatedProjects.relatedProject.*',
                'relatedProjects.relatedProject.project.uuid',
                'relatedProjects.relatedProject.project.*',
                'relatedProjects.project.uuid',
                'relatedProjects.project.*'
            ];
        } elseif ($relationField === 'relatedResearchOutputs') {
            $fields = [
                'relatedResearchOutputs.relatedResearchOutput.uuid',
                'relatedResearchOutputs.relatedResearchOutput.*',
                'relatedResearchOutputs.researchOutput.uuid',
                'relatedResearchOutputs.researchOutput.*'
            ];
        }

        $xml = '';
        foreach ($fields as $field) {
            $xml .= '<field>' . htmlspecialchars($field, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</field>';
        }

        return $xml;
    }

    /**
     * Extract only relation target UUIDs (projects or research outputs), not unrelated nested UUIDs.
     */
    protected static function extractRelatedEntityUuids($data, string $relationField): array
    {
        $targetKeys = $relationField === 'relatedProjects'
            ? ['relatedProject', 'project']
            : ['relatedResearchOutput', 'researchOutput'];

        $uuids = [];
        self::collectRelationTargetUuids($data, $targetKeys, false, $uuids);

        // Fallback for payload variants where the relation object itself carries the target UUID
        if (empty($uuids)) {
            $uuids = self::extractUuidObjectsRecursively($data);
        }

        return array_values(array_unique($uuids));
    }

    /**
     * Recursively collect UUIDs under nodes matching relation target keys.
     */
    protected static function collectRelationTargetUuids($data, array $targetKeys, bool $insideTarget, array &$uuids): void
    {
        if (!is_array($data)) {
            return;
        }

        if ($insideTarget) {
            $uuid = self::getArrayValue($data, 'uuid', '');
            if ($uuid === '') {
                $uuid = self::getArrayValue($data, '@attributes.uuid', '');
            }
            if ($uuid !== '') {
                $uuids[] = $uuid;
            }
        }

        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            $nextInsideTarget = $insideTarget || in_array((string)$key, $targetKeys, true);
            self::collectRelationTargetUuids($value, $targetKeys, $nextInsideTarget, $uuids);
        }
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
        $chooseSelector = (int)self::getArrayValue($settings, 'chooseSelector', -1);
        $selectorProjects = self::getArrayValue($settings, 'selectorProjects', '');

        // If no projects were set but search is given:
        if ($selectorProjects === '' && $narrowBySearch !== '') {
            return '';
        }

        if ($chooseSelector === 2) {
            // Build Projects query
            $projectTerms = [];
            $projectsArray = explode(',', $selectorProjects);

            foreach ($projectsArray as $project) {
                if (strpos($project, "|") !== false) {
                    $project = explode("|", $project)[0];
                }
                if (!empty($project)) {
                    $projectTerms[] = '"' . $project . '"';
                }
            }

            if (empty($projectTerms)) {
                return "<uuids><uuid>NO_DATASETS_FOUND</uuid></uuids>";
            }

            $xmlProjects = '<?xml version="1.0"?><projectsQuery>
    <size>99999</size>
    <linkingStrategy>string</linkingStrategy>
    <locales><locale>de_DE</locale></locales>
    <fields><field>relatedDataSets.uuid</field></fields>
    <orderings><ordering>title</ordering></orderings>
    <searchString>' . htmlspecialchars(implode(' OR ', $projectTerms), ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>
</projectsQuery>';

            $webservice = GeneralUtility::makeInstance(WebService::class);
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
        $webservice = GeneralUtility::makeInstance(WebService::class);
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
        $webservice = GeneralUtility::makeInstance(WebService::class);
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
