<?php

declare(strict_types=1);

namespace Univie\UniviePure\Utility;

use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Service\ApiServiceInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class ClassificationScheme
{
    private string $locale;
    private ApiServiceInterface $apiService;

    public function __construct(?ApiServiceInterface $apiService = null)
    {
        $this->locale = $this->getBackendUserLocale();
        $this->apiService = $apiService ?? GeneralUtility::makeInstance(ApiServiceInterface::class);
    }

    /**
     * Get the current backend user's locale using TYPO3 v12.4 best practices
     * Maps TYPO3 backend language to Pure API locale format
     */
    protected function getBackendUserLocale(): string
    {
        try {
            $languageServiceFactory = GeneralUtility::makeInstance(LanguageServiceFactory::class);
            $languageService = $languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
            $lang = $languageService->lang ?? '';

            if (empty($lang)) {
                $lang = $GLOBALS['BE_USER']->uc['lang'] ?? '';
            }

            if (empty($lang)) {
                $lang = 'de';
            }
        } catch (\Exception $e) {
            $lang = 'de';
        }

        $localeMap = [
            'de' => 'de_DE',
            'en' => 'en_GB',
            'default' => 'de_DE'
        ];

        return $localeMap[$lang] ?? $localeMap['default'];
    }

    /**
     * Extract localized name/title from Pure API response structure
     */
    private function extractLocalizedName(array $nameData, string $locale): string
    {
        if (isset($nameData['value']) && is_string($nameData['value'])) {
            return $nameData['value'];
        }

        if (isset($nameData[$locale]) && is_string($nameData[$locale])) {
            return $nameData[$locale];
        }

        if (!isset($nameData['text']) || !is_array($nameData['text'])) {
            return '';
        }

        $texts = $nameData['text'];
        if (isset($texts['value']) && is_string($texts['value'])) {
            $texts = [$texts];
        }
        $fallbackValue = '';

        foreach ($texts as $key => $text) {
            if (is_string($text)) {
                if ($fallbackValue === '') {
                    $fallbackValue = $text;
                }
                if (is_string($key) && ($key === $locale ||
                    (strpos($locale, '_') !== false && strpos($key, substr($locale, 0, 2)) === 0))) {
                    return $text;
                }
                continue;
            }

            if (!is_array($text) || !isset($text['value'])) {
                continue;
            }

            if (empty($fallbackValue)) {
                $fallbackValue = $text['value'];
            }

            if (isset($text['locale'])) {
                $textLocale = $text['locale'];
                if ($textLocale === $locale ||
                    (strpos($locale, '_') !== false && strpos($textLocale, substr($locale, 0, 2)) === 0) ||
                    (strpos($textLocale, '_') !== false && strpos($locale, substr($textLocale, 0, 2)) === 0)) {
                    return $text['value'];
                }
            }
        }

        return $fallbackValue;
    }

    /**
     * Resolve equipment label from multiple possible API field shapes.
     */
    private function getEquipmentLabel(array $equipment): string
    {
        $label = $this->extractLocalizedName($equipment['title'] ?? [], $this->locale);
        if ($label !== '') {
            return $label;
        }

        $label = $this->extractLocalizedName($equipment['name'] ?? [], $this->locale);
        if ($label !== '') {
            return $label;
        }

        if (isset($equipment['title']) && is_string($equipment['title'])) {
            return $equipment['title'];
        }
        if (isset($equipment['name']) && is_string($equipment['name'])) {
            return $equipment['name'];
        }

        return '';
    }

    /**
     * Resolve uuid from API payload.
     */
    private function getUuidFromItem(array $item): string
    {
        if (!empty($item['uuid']) && is_string($item['uuid'])) {
            return $item['uuid'];
        }
        return '';
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getOrganisations(&$config): void
    {
        $selectedUuids = $this->getCurrentlySelectedUuids('selectorOrganisations');

        $selectedItems = [];
        if (!empty($selectedUuids)) {
            $selectedItems = $this->getSelectedItemsWithRealNames($selectedUuids, 'org');
        }

        try {
            $response = $this->apiService->getOrganisationalUnits([
                'limit' => 8,
                'locale' => $this->locale,
            ]);
        } catch (\Throwable $e) {
            $this->addFlashMessage(
                'Could not fetch organisations from the API: ' . $e->getMessage(),
                'Organisations Fetch Failed',
                ContextualFeedbackSeverity::WARNING
            );
            foreach ($selectedItems as $item) {
                $config['items'][] = $item;
            }
            return;
        }

        foreach ($selectedItems as $item) {
            $config['items'][] = $item;
        }

        $existingUuids = array_column($selectedItems, 1);
        $items = $response['items'] ?? [];

        foreach ($items as $org) {
            $uuid = $org['uuid'] ?? '';
            if (empty($uuid) || in_array($uuid, $existingUuids)) {
                continue;
            }
            $name = $this->extractLocalizedName($org['name'] ?? [], $this->locale);
            if (!empty($name)) {
                $config['items'][] = [$name, $uuid];
            }
        }
    }

    public function getPersons(&$config): void
    {
        $selectedUuids = array_merge(
            $this->getCurrentlySelectedUuids('selectorPersons'),
            $this->getCurrentlySelectedUuids('selectorPersonsWithOrganization')
        );

        $selectedItems = [];
        if (!empty($selectedUuids)) {
            $selectedItems = $this->getSelectedItemsWithRealNames($selectedUuids, 'person');
        }

        try {
            $response = $this->apiService->getPersons([
                'limit' => 8,
                'locale' => $this->locale,
            ]);
        } catch (\Throwable $e) {
            $this->addFlashMessage(
                'Could not fetch persons from the API: ' . $e->getMessage(),
                'Persons Fetch Failed',
                ContextualFeedbackSeverity::WARNING
            );
            foreach ($selectedItems as $item) {
                $config['items'][] = $item;
            }
            return;
        }

        foreach ($selectedItems as $item) {
            $config['items'][] = $item;
        }

        $existingUuids = array_column($selectedItems, 1);
        $items = $response['items'] ?? [];

        foreach ($items as $person) {
            $uuid = $person['uuid'] ?? '';
            if (empty($uuid) || in_array($uuid, $existingUuids)) {
                continue;
            }

            $personName = ($person['name']['lastName'] ?? '') . ', ' . ($person['name']['firstName'] ?? '');
            $organizationNames = $this->getActiveOrganizationNames($person);

            if (!empty($organizationNames)) {
                $displayName = $personName . ' (' . implode(', ', $organizationNames) . ')';
            } else {
                $displayName = $personName;
            }

            $config['items'][] = [$displayName, $uuid];
        }
    }

    private function getActiveOrganizationNames(array $person): array
    {
        $organizationNames = [];

        $associations = $person['staffOrganizationAssociations']
            ?? $person['honoraryStaffOrganisationAssociations']
            ?? $person['organizationAssociations']
            ?? [];

        if (isset($associations['organisationalUnit']) || isset($associations['organizationalUnit'])) {
            $associations = [$associations];
        }

        foreach ($associations as $association) {
            if (isset($association['period']['endDate']) &&
                !empty($association['period']['endDate']) &&
                strtotime($association['period']['endDate']) < time()) {
                continue;
            }

            $orgUnit = $association['organisationalUnit'] ?? $association['organizationalUnit'] ?? [];

            if (isset($orgUnit['name'])) {
                $orgName = $this->extractLocalizedName($orgUnit['name'], $this->locale);

                if (!empty($orgName) && !in_array($orgName, $organizationNames)) {
                    $organizationNames[] = $orgName;
                }
            }
        }

        return $organizationNames;
    }

    public function getProjects(&$config): void
    {
        $selectedUuids = $this->getCurrentlySelectedUuids('selectorProjects');

        $selectedItems = [];
        if (!empty($selectedUuids)) {
            $selectedItems = $this->getSelectedItemsWithRealNames($selectedUuids, 'project');
        }

        try {
            $response = $this->apiService->getProjects([
                'limit' => 8,
                'locale' => $this->locale,
            ]);
        } catch (\Throwable $e) {
            $this->addFlashMessage(
                'Could not fetch projects from the API: ' . $e->getMessage(),
                'Projects Fetch Failed',
                ContextualFeedbackSeverity::WARNING
            );
            foreach ($selectedItems as $item) {
                $config['items'][] = $item;
            }
            return;
        }

        foreach ($selectedItems as $item) {
            $config['items'][] = $item;
        }

        $existingUuids = array_column($selectedItems, 1);
        $items = $response['items'] ?? [];

        foreach ($items as $project) {
            $uuid = $project['uuid'] ?? '';
            if (empty($uuid) || in_array($uuid, $existingUuids)) {
                continue;
            }

            $title = $this->extractLocalizedName($project['title'] ?? [], $this->locale);
            if (empty($title)) {
                $title = 'Unknown Project';
            }

            if (!empty($project['acronym']) && strpos($title, $project['acronym']) === false) {
                $title = $project['acronym'] . ' - ' . $title;
            }
            $config['items'][] = [$title, $uuid];
        }
    }

    public function getEquipments(&$config): void
    {
        $selectedUuids = $this->getCurrentlySelectedUuids('selectorEquipments');

        $selectedItems = [];
        if (!empty($selectedUuids)) {
            $selectedItems = $this->getSelectedItemsWithRealNames($selectedUuids, 'equipment');
        }

        try {
            $response = $this->apiService->getEquipments([
                'limit' => 8,
                'locale' => $this->locale,
            ]);
        } catch (\Throwable $e) {
            $this->addFlashMessage(
                'Could not fetch equipments from the API: ' . $e->getMessage(),
                'Equipment Fetch Failed',
                ContextualFeedbackSeverity::WARNING
            );
            foreach ($selectedItems as $item) {
                $config['items'][] = $item;
            }
            return;
        }

        foreach ($selectedItems as $item) {
            $config['items'][] = $item;
        }

        $existingUuids = array_column($selectedItems, 1);
        $items = $response['items'] ?? [];

        foreach ($items as $equipment) {
            $uuid = $this->getUuidFromItem($equipment);
            if ($uuid === '' || in_array($uuid, $existingUuids, true)) {
                continue;
            }
            $label = $this->getEquipmentLabel($equipment);
            if (!empty($label)) {
                $config['items'][] = [$label, $uuid];
            }
        }
    }

    public function getTypesFromPublications(&$config): void
    {
        try {
            $publicationTypes = $this->apiService->getResearchOutputTypes();
        } catch (\Throwable $e) {
            $this->addFlashMessage(
                'Could not fetch publication types from the API: ' . $e->getMessage(),
                'Publication Types Fetch Failed',
                ContextualFeedbackSeverity::WARNING
            );
            return;
        }

        $this->classificationRefs2items($publicationTypes, $config);
    }

    public function getEquipmentTypes(&$config): void
    {
        try {
            $equipmentTypes = $this->apiService->getEquipmentTypes();
        } catch (\Throwable $e) {
            $this->addFlashMessage(
                'Could not fetch equipment types from the API: ' . $e->getMessage(),
                'Equipment Types Fetch Failed',
                ContextualFeedbackSeverity::WARNING
            );
            return;
        }

        $this->classificationRefs2items($equipmentTypes, $config);
    }

    public function classificationRefs2items(array $classificationRefList, array &$config): void
    {
        $classifications = $classificationRefList['classifications'] ?? [];
        if (!is_array($classifications)) {
            return;
        }

        foreach ($classifications as $classification) {
            if (!is_array($classification)) {
                continue;
            }

            $uri = $classification['uri'] ?? '';
            if ($uri === '') {
                continue;
            }

            $title = $this->extractLocalizedName($classification['term'] ?? [], $this->locale);
            if ($title === '') {
                $title = $classification['term']['text'][0]['value']
                    ?? $classification['term']['value']
                    ?? $uri;
            }

            if ($title === '<placeholder>') {
                continue;
            }

            $config['items'][] = [$title, $uri];
        }
    }

    public function sorted2items($sorted, &$config): void
    {
        foreach ($sorted as $optGroup) {
            $config['items'][] = [
                '----- ' . $optGroup['title'] . ': -----',
                '--div--'
            ];
            foreach ($optGroup['child'] as $opt) {
                $config['items'][] = [
                    $opt['title'],
                    $opt['uri']
                ];
            }
        }
    }

    public function sortClassification($unsorted): array
    {
        if (!isset($unsorted['items'][0]['containedClassifications'])) {
            return [];
        }

        return array_values(array_filter(
            array_map(function ($parent) use ($unsorted) {
                if (($parent['disabled'] ?? false) || !$this->classificationHasChild($parent)) {
                    return null;
                }

                $children = [];
                if (isset($parent['classificationRelations'])) {
                    $children = array_values(array_filter(
                        array_map(function ($relation) use ($unsorted) {
                            if (($relation['relationType']['uri'] ?? '') !== '/dk/atira/pure/core/hierarchies/child') {
                                return null;
                            }

                            $relatedTo = $relation['relatedTo'] ?? [];
                            if (isset($relatedTo[0]) && is_array($relatedTo[0])) {
                                $relatedTo = $relatedTo[0];
                            }

                            $relatedUri = $relatedTo['uri'] ?? '';
                            if ($this->isChildEnabledOnRootLevel($unsorted, $relatedUri)) {
                                return null;
                            }

                            $title = $this->extractLocalizedName($relatedTo['term'] ?? [], $this->locale);
                            if ($title === '') {
                                $title = $relatedTo['term']['text'][0]['value'] ?? '';
                            }

                            return [
                                'uri' => $relatedUri,
                                'title' => $title
                            ];
                        }, $parent['classificationRelations'])
                    ));
                }

                if (empty($children)) {
                    return null;
                }

                return [
                    'uri' => $parent['uri'],
                    'title' => $this->extractLocalizedName($parent['term'] ?? [], $this->locale)
                        ?: ($parent['term']['text'][0]['value'] ?? 'Unknown title'),
                    'child' => $children
                ];
            }, $unsorted['items'][0]['containedClassifications'])
        ));
    }

    private function classificationHasChild($parent): bool
    {
        if (!isset($parent['classificationRelations'])) {
            return false;
        }

        foreach ($parent['classificationRelations'] as $child) {
            $relatedTo = $child['relatedTo'] ?? [];
            if (isset($relatedTo[0]) && is_array($relatedTo[0])) {
                $relatedTo = $relatedTo[0];
            }

            $title = $this->extractLocalizedName($relatedTo['term'] ?? [], $this->locale);
            if ($title === '') {
                $title = $relatedTo['term']['text'][0]['value'] ?? '';
            }

            if (($child['relationType']['uri'] ?? '') === '/dk/atira/pure/core/hierarchies/child'
                && $title !== '<placeholder>'
            ) {
                return true;
            }
        }
        return false;
    }

    private function isChildEnabledOnRootLevel($roots, $childUri): bool
    {
        foreach ($roots['items'][0]['containedClassifications'] as $root) {
            if ($root['uri'] === $childUri) {
                return $root['disabled'] ?? false;
            }
        }
        return false;
    }

    public function getUuidForEmail(string $email): string
    {
        try {
            $response = $this->apiService->getPersons([
                'search' => $email,
                'limit' => 1,
                'locale' => $this->locale,
            ]);

            if (isset($response['count']) && $response['count'] === 1 && isset($response['items'][0]['uuid'])) {
                return $response['items'][0]['uuid'];
            }
        } catch (\Throwable $e) {
            // Fall through to default
        }

        return '123456789';
    }

    public function getItemsToChoose(&$config, $PA): void
    {
        $languageService = $GLOBALS['LANG'];

        $config['items'][] = [
            $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectBlank'),
            -1
        ];

        $settings = $config['flexParentDatabaseRow']['pi_flexform'];
        $whatToDisplay = $settings['data']['sDEF']['lDEF']['settings.what_to_display']['vDEF'][0] ?? '';

        switch ($whatToDisplay) {
            case 'PUBLICATIONS':
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByUnit'),
                    0
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByPerson'),
                    1
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByProject'),
                    2
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByEquipment'),
                    4
                ];
                break;

            case 'PROJECTS':
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByUnit'),
                    0
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByPerson'),
                    1
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByEquipment'),
                    4
                ];
                break;

            case 'EQUIPMENTS':
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByUnit'),
                    0
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByPerson'),
                    1
                ];
                break;

            case 'DATASETS':
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByUnit'),
                    0
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByPerson'),
                    1
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByProject'),
                    2
                ];
                break;

            default:
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByUnit'),
                    0
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByPerson'),
                    1
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:flexform.common.selectByProject'),
                    2
                ];
                break;
        }
    }

    protected function addFlashMessage(
        string                     $message,
        string                     $title,
        ContextualFeedbackSeverity $severity
    ): void {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $severity
        );

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->enqueue($flashMessage);
    }

    protected function getCurrentlySelectedUuids(string $fieldName): array
    {
        $uuids = [];

        if (!empty($_POST['data']['tt_content'])) {
            foreach ($_POST['data']['tt_content'] as $uid => $record) {
                if (isset($record['pi_flexform']['data']['Common']['lDEF']["settings.$fieldName"]['vDEF'])) {
                    $values = $record['pi_flexform']['data']['Common']['lDEF']["settings.$fieldName"]['vDEF'];
                    if (is_array($values)) {
                        $uuids = array_merge($uuids, array_filter($values));
                    } elseif (is_string($values) && !empty($values)) {
                        $uuids = array_merge($uuids, explode(',', $values));
                    }
                }
            }
        }

        if (empty($uuids) && !empty($_GET['edit']['tt_content'])) {
            $editUid = key($_GET['edit']['tt_content']);
            if ($editUid) {
                $queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
                    ->getQueryBuilderForTable('tt_content');

                $record = $queryBuilder
                    ->select('pi_flexform')
                    ->from('tt_content')
                    ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($editUid, \PDO::PARAM_INT)))
                    ->executeQuery()
                    ->fetchAssociative();

                if ($record && !empty($record['pi_flexform'])) {
                    $flexFormData = GeneralUtility::xml2array($record['pi_flexform']);
                    if ($flexFormData && isset($flexFormData['data']['Common']['lDEF']["settings.$fieldName"]['vDEF'])) {
                        $values = $flexFormData['data']['Common']['lDEF']["settings.$fieldName"]['vDEF'];
                        if (is_string($values) && !empty($values)) {
                            $uuids = explode(',', $values);
                        }
                    }
                }
            }
        }

        $normalizedUuids = [];
        foreach ($uuids as $uuid) {
            $normalized = $this->normalizeSelectedIdentifier((string)$uuid);
            if ($normalized !== '') {
                $normalizedUuids[] = $normalized;
            }
        }

        return array_values(array_unique($normalizedUuids));
    }

    private function normalizeSelectedIdentifier(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '|')) {
            $value = explode('|', $value, 2)[0];
        }
        return trim($value);
    }

    protected function fetchOrganizationByUuid(string $uuid): ?array
    {
        try {
            $response = $this->apiService->getOrganisationalUnits([
                'search' => $uuid,
                'limit' => 1,
                'locale' => $this->locale,
            ]);

            if (isset($response['items'][0])) {
                $name = $this->extractLocalizedName($response['items'][0]['name'] ?? [], $this->locale);
                if (!empty($name)) {
                    return ['name' => $name];
                }
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        return null;
    }

    protected function fetchPersonByUuid(string $uuid): ?array
    {
        try {
            $person = $this->apiService->getPerson($uuid, ['locale' => $this->locale]);

            if (is_array($person)) {
                $personName = ($person['name']['lastName'] ?? '') . ', ' . ($person['name']['firstName'] ?? '');
                $organizationNames = $this->getActiveOrganizationNames($person);

                if (!empty($organizationNames)) {
                    $personName .= ' (' . implode(', ', $organizationNames) . ')';
                }

                return ['name' => $personName];
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        return null;
    }

    protected function fetchProjectByUuid(string $uuid): ?array
    {
        try {
            $project = $this->apiService->getProject($uuid, ['locale' => $this->locale]);

            if (is_array($project)) {
                $title = $this->extractLocalizedName($project['title'] ?? [], $this->locale);

                if (empty($title)) {
                    $title = 'Unknown Project';
                }

                if (!empty($project['acronym']) && strpos($title, $project['acronym']) === false) {
                    $title = $project['acronym'] . ' - ' . $title;
                }

                return ['title' => $title];
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        return null;
    }

    protected function fetchEquipmentByUuid(string $uuid): ?array
    {
        try {
            $response = $this->apiService->getEquipments([
                'search' => $uuid,
                'limit' => 1,
                'locale' => $this->locale,
            ]);

            $items = $response['items'] ?? [];
            foreach ($items as $equipment) {
                $itemUuid = $this->getUuidFromItem($equipment);
                if ($itemUuid !== '' && $itemUuid !== $uuid) {
                    continue;
                }
                $title = $this->getEquipmentLabel($equipment);
                if ($title !== '') {
                    return ['title' => $title];
                }
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        return null;
    }

    protected function getSelectedItemsWithRealNames(array $uuids, string $type): array
    {
        $items = [];

        foreach ($uuids as $uuid) {
            $uuid = $this->normalizeSelectedIdentifier((string)$uuid);
            if ($uuid === '') {
                continue;
            }

            try {
                $realName = null;
                switch ($type) {
                    case 'org':
                        $item = $this->fetchOrganizationByUuid($uuid);
                        if ($item) {
                            $realName = $item['name'];
                        }
                        break;

                    case 'person':
                        $item = $this->fetchPersonByUuid($uuid);
                        if ($item) {
                            $realName = $item['name'];
                        }
                        break;

                    case 'project':
                        $item = $this->fetchProjectByUuid($uuid);
                        if ($item) {
                            $realName = $item['title'];
                        }
                        break;

                    case 'equipment':
                        $item = $this->fetchEquipmentByUuid($uuid);
                        if ($item) {
                            $realName = $item['title'];
                        }
                        break;
                }

                if ($realName) {
                    $items[] = [$realName, $uuid];
                } else {
                    $placeholder = '[' . ucfirst($type) . ': ' . substr($uuid, 0, 8) . '...]';
                    $items[] = [$placeholder, $uuid];
                }
            } catch (\Exception $e) {
                $placeholder = '[' . ucfirst($type) . ': ' . substr($uuid, 0, 8) . '...]';
                $items[] = [$placeholder, $uuid];
            }
        }

        return $items;
    }
}
