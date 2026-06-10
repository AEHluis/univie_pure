<?php

namespace Univie\UniviePure\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Core\Environment;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/**
 * Common utility helpers
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
        if (!is_array($array)) {
            return $default;
        }

        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Extract organization UUIDs from settings
     */
    public static function getOrganizationUuids(array $settings): array
    {
        $chooseSelector = (int)self::getArrayValue($settings, 'chooseSelector', -1);
        if ($chooseSelector !== 0) {
            return [];
        }

        $selectorOrganisations = self::getArrayValue($settings, 'selectorOrganisations', '');
        if ($selectorOrganisations === '') {
            return [];
        }

        $uuids = [];
        $organisations = explode(',', $selectorOrganisations);
        foreach ($organisations as $org) {
            $org = trim($org);
            if (strpos($org, '|') !== false) {
                $org = explode('|', $org)[0];
            }
            if (!empty($org)) {
                $uuids[] = $org;
            }
        }

        return $uuids;
    }

    /**
     * Extract person UUIDs from settings
     */
    public static function getPersonUuids(array $settings): array
    {
        $chooseSelector = (int)self::getArrayValue($settings, 'chooseSelector', -1);
        if ($chooseSelector !== 1 && $chooseSelector !== 3) {
            return [];
        }

        $selectorPersons = $chooseSelector === 3
            ? self::getArrayValue($settings, 'selectorPersonsWithOrganization', '')
            : self::getArrayValue($settings, 'selectorPersons', '');

        if ($selectorPersons === '') {
            return [];
        }

        $uuids = [];
        $persons = explode(',', $selectorPersons);
        foreach ($persons as $person) {
            $person = trim($person);
            if (strpos($person, '|') !== false) {
                $person = explode('|', $person)[0];
            }
            if (!empty($person)) {
                $uuids[] = $person;
            }
        }

        return $uuids;
    }

    /**
     * Extract project UUIDs from settings
     */
    public static function getProjectUuids(array $settings): array
    {
        $chooseSelector = (int)self::getArrayValue($settings, 'chooseSelector', -1);
        if ($chooseSelector !== 2) {
            return [];
        }

        $selectorProjects = self::getArrayValue($settings, 'selectorProjects', '');
        if ($selectorProjects === '') {
            return [];
        }

        $uuids = [];
        $projects = explode(',', $selectorProjects);
        foreach ($projects as $project) {
            $project = trim($project);
            if (strpos($project, '|') !== false) {
                $project = explode('|', $project)[0];
            }
            if (!empty($project)) {
                $uuids[] = $project;
            }
        }

        return $uuids;
    }

    /**
     * Extract equipment UUIDs from settings
     */
    public static function getEquipmentUuids(array $settings): array
    {
        $chooseSelector = (int)self::getArrayValue($settings, 'chooseSelector', -1);
        if ($chooseSelector !== 4) {
            return [];
        }

        $selectorEquipments = self::getArrayValue($settings, 'selectorEquipments', '');
        if ($selectorEquipments === '') {
            return [];
        }

        $uuids = [];
        $equipments = explode(',', $selectorEquipments);
        foreach ($equipments as $equipment) {
            $equipment = trim($equipment);
            if (strpos($equipment, '|') !== false) {
                $equipment = explode('|', $equipment)[0];
            }
            if (!empty($equipment)) {
                $uuids[] = $equipment;
            }
        }

        return $uuids;
    }

    /**
     * Get filter parameters from settings for API calls
     */
    public static function buildFilterParams(array $settings): array
    {
        $params = [];

        // Search filter
        $narrowBySearch = self::getArrayValue($settings, 'narrowBySearch', '');
        $filter = self::getArrayValue($settings, 'filter', '');
        $search = trim($narrowBySearch . ' ' . $filter);
        if ($search !== '') {
            $params['search'] = $search;
        }

        // Organization filter
        $orgUuids = self::getOrganizationUuids($settings);
        if (!empty($orgUuids)) {
            $params['organizationUuids'] = $orgUuids;
            $params['includeSubUnits'] = (int)self::getArrayValue($settings, 'includeSubUnits', 0) === 1;
        }

        // Person filter
        $personUuids = self::getPersonUuids($settings);
        if (!empty($personUuids)) {
            $params['personUuids'] = $personUuids;
        }

        // Project filter
        $projectUuids = self::getProjectUuids($settings);
        if (!empty($projectUuids)) {
            $params['projectUuids'] = $projectUuids;
        }

        // Equipment filter
        $equipmentUuids = self::getEquipmentUuids($settings);
        if (!empty($equipmentUuids)) {
            $params['equipmentUuids'] = $equipmentUuids;
        }

        return $params;
    }

    /**
     * Sanitize a search string for safe use in API queries.
     */
    public static function cleanSearchString(string $content): string
    {
        $content = strtolower($content);
        $content = substr($content, 0, 500);
        $content = filter_var($content, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);
        $content = preg_replace('/\s+/', ' ', trim($content));
        $content = preg_replace('/[<>"\';&\x00-\x1F\x7F]/u', '', $content);
        $content = preg_replace("/\(([^()]*+|(?R))*\)/", " ", $content);
        $content = preg_replace('/[^\p{L}\p{N} .–_]/u', " ", urldecode($content));
        return $content;
    }

    /**
     * Extract localized text from Pure API name/title structures
     */
    public static function extractLocalizedText($fieldData, string $locale): string
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
}
