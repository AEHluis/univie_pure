<?php
declare(strict_types=1);

namespace Univie\UniviePure\Utility;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Service\ApiServiceFactory;
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
    private const RESEARCHOUTPUT = '/dk/atira/pure/researchoutput/researchoutputtypes';
    private const PROJECTS = '/dk/atira/pure/upm/fundingprogramme';

    private string $locale;
    private ApiServiceInterface $apiService;

    public function __construct(ApiServiceFactory $apiServiceFactory)
    {
        $this->locale = $this->getBackendUserLocale();
        // Use configured API (respects PURE_API_VERSION environment variable)
        $this->apiService = $apiServiceFactory->create();
    }
    
    /**
     * Get the current backend user's locale using TYPO3 v12.4 best practices
     * Maps TYPO3 backend language to Pure API locale format
     */
    protected function getBackendUserLocale(): string
    {
        try {
            // Method 1: TYPO3 v12.4 recommended - LanguageServiceFactory
            $languageServiceFactory = GeneralUtility::makeInstance(LanguageServiceFactory::class);
            $languageService = $languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
            $lang = $languageService->lang ?? '';
            
            // Method 2: Fallback to direct BE_USER access
            if (empty($lang)) {
                $lang = $GLOBALS['BE_USER']->uc['lang'] ?? '';
            }
            
            // Default to German for this system if nothing found
            if (empty($lang)) {
                $lang = 'de'; // Set German as default for this University system
            }
            
        } catch (\Exception $e) {
            // Fallback in case of any errors
            $lang = 'de';
        }
        
        // Map TYPO3 language codes to Pure API locale format
        $localeMap = [
            'de' => 'de_DE',
            'en' => 'en_GB',
            'default' => 'de_DE' // German default for University system
        ];
        
        return $localeMap[$lang] ?? $localeMap['default'];
    }
    
    /**
     * Get localized search hint text
     */
    protected function getSearchHintText(): string
    {
        return $this->locale === 'de_DE' ? 
            '→ Suchen für mehr...' : 
            '→ Search for more...';
    }
    
    /**
     * Extract localized name/title from Pure API response structure
     * Same logic as in AjaxController for consistency
     */
    private function extractLocalizedName(array $nameData, string $locale): string
    {
        // If there's no text field, return empty
        if (!isset($nameData['text']) || !is_array($nameData['text'])) {
            return '';
        }
        
        $texts = $nameData['text'];
        $fallbackValue = '';
        
        // Look for the text entry with matching locale
        foreach ($texts as $text) {
            if (!is_array($text) || !isset($text['value'])) {
                continue;
            }
            
            // Store first value as fallback
            if (empty($fallbackValue)) {
                $fallbackValue = $text['value'];
            }
            
            // If locale matches, return this value
            // Try exact match first, then partial match (e.g., 'de' in 'de_DE')
            if (isset($text['locale'])) {
                $textLocale = $text['locale'];
                if ($textLocale === $locale || 
                    (strpos($locale, '_') !== false && strpos($textLocale, substr($locale, 0, 2)) === 0) ||
                    (strpos($textLocale, '_') !== false && strpos($locale, substr($textLocale, 0, 2)) === 0)) {
                    return $text['value'];
                }
            }
        }
        
        // Return fallback if no locale match found
        return $fallbackValue;
    }

    // Add this method to your ClassificationScheme class
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getOrganisations(&$config): void
    {
        // Always load ALL selected items to ensure they remain available
        // This is the only reliable way in TYPO3 to preserve selections
        $selectedUuids = $this->getCurrentlySelectedUuids('selectorOrganisations');

        // Fetch real names for selected items
        $selectedItems = [];
        if (!empty($selectedUuids)) {
            $selectedItems = $this->getSelectedItemsWithRealNames($selectedUuids, 'org');
        }

        // Use unified API interface (respects PURE_API_VERSION environment variable)
        $organisations = $this->apiService->getOrganisationalUnits([
            'size' => 8,
            'locale' => $this->locale,
            'fields' => ['uuid', 'name.text.value'],
            'ordering' => 'name',
            'returnUsedContent' => 'true'
        ]);

        // Debug: Log actual API response count
        if ($organisations && isset($organisations['items'])) {
            error_log('[Pure Debug] Organizations API returned: ' . count($organisations['items']) . ' items (requested 8)');
        }

        if (!$organisations || !isset($organisations['items'])) {
            $this->addFlashMessage(
                'Could not fetch organisations from the API. Please check your connection.',
                'Organisations Fetch Failed',
                ContextualFeedbackSeverity::WARNING
            );
            return;
        }

        // Add selected items first (highest priority)
        foreach ($selectedItems as $item) {
            $config['items'][] = $item;
        }

        // Then add fresh items from API (avoiding duplicates)
        $existingUuids = array_column($selectedItems, 1);
        if (is_array($organisations) && isset($organisations['items'])) {
            foreach ($organisations['items'] as $org) {
                if (!in_array($org['uuid'], $existingUuids)) {
                    $name = $this->extractLocalizedName($org['name'] ?? [], $this->locale);
                    if (!empty($name)) {
                        $config['items'][] = [$name, $org['uuid']];
                    }
                }
            }
        }

        // Add hint for users to search
        $searchHint = $this->getSearchHintText();
        $config['items'][] = ['─────────────────────', '--div--'];
        $config['items'][] = [$searchHint, ''];

        // Debug: Log final item count (excluding separators)
        $actualItemCount = count($config['items']) - 2; // Minus separator and hint
        error_log('[Pure Debug] Organizations final dropdown items: ' . $actualItemCount);
    }

    public function getPersons(&$config): void
    {
        // Always load ALL selected items to ensure they remain available
        // Handle both field names since this method is used for both selectors
        $selectedUuids = array_merge(
            $this->getCurrentlySelectedUuids('selectorPersons'),
            $this->getCurrentlySelectedUuids('selectorPersonsWithOrganization')
        );

        // Fetch real names for selected items
        $selectedItems = [];
        if (!empty($selectedUuids)) {
            $selectedItems = $this->getSelectedItemsWithRealNames($selectedUuids, 'person');
        }

        // Use unified API interface (respects PURE_API_VERSION environment variable)
        $persons = $this->apiService->getPersons([
            'size' => 8,
            'locale' => $this->locale,
            'fields' => [
                'uuid',
                'name.*',
                'honoraryStaffOrganisationAssociations.uuid',
                'honoraryStaffOrganisationAssociations.period.*',
                'honoraryStaffOrganisationAssociations.organisationalUnit.uuid',
                'honoraryStaffOrganisationAssociations.organisationalUnit.name.*'
            ],
            'ordering' => 'lastName',
            'employmentStatus' => 'ACTIVE'
        ]);

        // Debug: Log actual API response count
        if ($persons && isset($persons['items'])) {
            error_log('[Pure Debug] Persons API returned: ' . count($persons['items']) . ' items (requested 8)');
        }

        if (!$persons || !isset($persons['items'])) {
            $this->addFlashMessage(
                'Could not fetch Person data with organization associations from the API. Please check your connection.',
                'Person Organization Data Fetch Failed',
                ContextualFeedbackSeverity::WARNING
            );
            return;
        }

        // Add selected items first (highest priority)
        foreach ($selectedItems as $item) {
            $config['items'][] = $item;
        }

        // Then add fresh items from API (avoiding duplicates)
        $existingUuids = array_column($selectedItems, 1);
        if (is_array($persons) && isset($persons['items'])) {
            foreach ($persons['items'] as $person) {
                if (!in_array($person['uuid'], $existingUuids)) {
                    $personName = $person['name']['lastName'] . ', ' . $person['name']['firstName'];
                    $organizationNames = $this->getActiveOrganizationNames($person);

                    if (!empty($organizationNames)) {
                        $displayName = $personName . ' (' . implode(', ', $organizationNames) . ')';
                    } else {
                        $displayName = $personName;
                    }

                    $config['items'][] = [$displayName, $person['uuid']];
                }
            }
        }

        // Add hint for users to search
        $searchHint = $this->getSearchHintText();
        $config['items'][] = ['─────────────────────', '--div--'];
        $config['items'][] = [$searchHint, ''];
    }

    private function getActiveOrganizationNames(array $person): array
    {
        $organizationNames = [];
        
        if (isset($person['honoraryStaffOrganisationAssociations']) && is_array($person['honoraryStaffOrganisationAssociations'])) {
            foreach ($person['honoraryStaffOrganisationAssociations'] as $association) {
                // Check if association is active (no endDate or endDate is in the future)
                $isActive = !isset($association['period']['endDate']) || 
                           strtotime($association['period']['endDate']) > time();
                
                if ($isActive && isset($association['organisationalUnit']['name'])) {
                    // Use the extractLocalizedName method for consistent locale handling
                    $orgName = $this->extractLocalizedName($association['organisationalUnit']['name'], $this->locale);
                    
                    if (!in_array($orgName, $organizationNames)) {
                        $organizationNames[] = $orgName;
                    }
                }
            }
        }
        
        return $organizationNames;
    }

    public function getProjects(&$config): void
    {
        // Always load ALL selected items to ensure they remain available
        $selectedUuids = $this->getCurrentlySelectedUuids('selectorProjects');

        // Fetch real names for selected items
        $selectedItems = [];
        if (!empty($selectedUuids)) {
            $selectedItems = $this->getSelectedItemsWithRealNames($selectedUuids, 'project');
        }

        // Use unified API interface (respects PURE_API_VERSION environment variable)
        $projects = $this->apiService->getProjects([
            'size' => 8,
            'locale' => $this->locale,
            'fields' => ['uuid', 'acronym', 'title.*'],
            'ordering' => 'title',
            'workflowSteps' => ['validated']
        ]);

        // Debug: Log actual API response count
        if ($projects && isset($projects['items'])) {
            error_log('[Pure Debug] Projects API returned: ' . count($projects['items']) . ' items (requested 8)');
        }

        if (!$projects || !isset($projects['items'])) {
            $this->addFlashMessage(
                'Could not fetch projects from the API. Please check your connection.',
                'Projects Fetch Failed',
                ContextualFeedbackSeverity::WARNING
            );
            return;
        }

        // Add selected items first (highest priority)
        foreach ($selectedItems as $item) {
            $config['items'][] = $item;
        }

        // Then add fresh items from API (avoiding duplicates)
        $existingUuids = array_column($selectedItems, 1);
        if (is_array($projects) && isset($projects['items'])) {
            foreach ($projects['items'] as $project) {
                if (!in_array($project['uuid'], $existingUuids)) {
                    $title = $this->extractLocalizedName($project['title'] ?? [], $this->locale);
                    if (empty($title)) {
                        $title = 'Unknown Project';
                    }

                    if (!empty($project['acronym']) && strpos($title, $project['acronym']) === false) {
                        $title = $project['acronym'] . ' - ' . $title;
                    }
                    $config['items'][] = [$title, $project['uuid']];
                }
            }
        }

        // Add hint for users to search
        $searchHint = $this->getSearchHintText();
        $config['items'][] = ['─────────────────────', '--div--'];
        $config['items'][] = [$searchHint, ''];
    }

    public function getTypesFromPublications(&$config): void
    {
        $classificationXML = trim('<?xml version="1.0"?>
            <classificationSchemesQuery>
            <size>99999</size>
            <offset>0</offset>
            <locales>
            <locale>' . htmlspecialchars($this->locale, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</locale>
            </locales>
            <returnUsedContent>true</returnUsedContent>
            <navigationLink>true</navigationLink> 
            <baseUri>' . self::RESEARCHOUTPUT . '</baseUri>
            </classificationSchemesQuery>');

        // Fetch fresh publication types data
        $publicationTypes = $this->webService->getJson('classification-schemes', $classificationXML);

        if (is_array($publicationTypes)) {
            $sorted = $this->sortClassification($publicationTypes);
            $this->sorted2items($sorted, $config);
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
                            if ($relation['relationType']['uri'] !== '/dk/atira/pure/core/hierarchies/child') {
                                return null;
                            }

                            $relatedUri = $relation['relatedTo'][0]['uri'] ?? '';
                            if ($this->isChildEnabledOnRootLevel($unsorted, $relatedUri)) {
                                return null;
                            }

                            return [
                                'uri' => $relation['relatedTo']['uri'] ?? '',
                                'title' => $relation['relatedTo']['term']['text'][0]['value'] ?? ''
                            ];
                        }, $parent['classificationRelations'])
                    ));
                }

                if (empty($children)) {
                    return null;
                }

                return [
                    'uri' => $parent['uri'],
                    'title' => $parent['term']['text'][0]['value'] ?? 'Unknown title',
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
            if ($child['relationType']['uri'] === '/dk/atira/pure/core/hierarchies/child'
                && $child['relatedTo']['term']['text'][0]['value'] !== '<placeholder>'
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
        $xml = '<?xml version="1.0"?>
            <personsQuery>
            <searchString>' . htmlspecialchars($email, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>
            <locales>
            <locale>' . $this->locale . '</locale>
            </locales>
            <fields>name</fields>
            </personsQuery>';

        $uuids = $this->webService->getXml('persons', $xml);

        if (isset($uuids['count']) && $uuids['count'] === 1) {
            return $uuids['person']['@attributes']['uuid'];
        }

        return '123456789'; // Default fallback UUID
    }

    public function getItemsToChoose(&$config, $PA): void
    {
        $languageService = $GLOBALS['LANG'];

        // Always start with a blank option
        $config['items'][] = [
            $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectBlank'),
            -1
        ];
        
        // Get the current display type
        $settings = $config['flexParentDatabaseRow']['pi_flexform'];
        $whatToDisplay = $settings['data']['sDEF']['lDEF']['settings.what_to_display']['vDEF'][0] ?? '';
        
        // Configure options based on display type
        switch ($whatToDisplay) {
            case 'PUBLICATIONS':
                // Publications: Organizations, Persons, Projects
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByUnit'),
                    0
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByPerson'),
                    1
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByProject'),
                    2
                ];
                // Note: PersonWithOrganization (3) is intentionally not shown for cleaner UI
                break;
                
            case 'PROJECTS':
                // Projects: Organizations, Persons (no projects)
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByUnit'),
                    0
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByPerson'),
                    1
                ];
                break;
                
            case 'EQUIPMENTS':
                // Equipment: Organizations, Persons (no projects)
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByUnit'),
                    0
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByPerson'),
                    1
                ];
                break;
                
            case 'DATASETS':
                // Datasets: Organizations, Persons, Projects
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByUnit'),
                    0
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByPerson'),
                    1
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByProject'),
                    2
                ];
                break;
                
            default:
                // Default: show all options for safety
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByUnit'),
                    0
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByPerson'),
                    1
                ];
                $config['items'][] = [
                    $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByProject'),
                    2
                ];
                break;
        }
    }

    /**
     * Display a FlashMessage in the TYPO3 Backend.
     *
     * @param string $message The message to display
     * @param string $title The title for the message
     * @param ContextualFeedbackSeverity $severity The severity of the message
     */
    protected function addFlashMessage(
        string                     $message,
        string                     $title,
        ContextualFeedbackSeverity $severity
    ): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $severity
        );

        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->enqueue($flashMessage);
    }

    /**
     * Get currently selected UUIDs for a specific field
     * Uses multiple fallback methods to find current selection
     */
    protected function getCurrentlySelectedUuids(string $fieldName): array
    {
        $uuids = [];

        // Get PSR-7 request
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request) {
            return [];
        }

        // Get parsed body (POST data) and query params (GET data)
        $parsedBody = $request->getParsedBody();
        $queryParams = $request->getQueryParams();

        // Method 1: Check POST data (form submission)
        if (!empty($parsedBody['data']['tt_content']) && is_array($parsedBody['data']['tt_content'])) {
            foreach ($parsedBody['data']['tt_content'] as $uid => $record) {
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

        // Method 2: Check GET parameters (edit mode)
        if (empty($uuids) && !empty($queryParams['edit']['tt_content']) && is_array($queryParams['edit']['tt_content'])) {
            $editUid = key($queryParams['edit']['tt_content']);
            if ($editUid) {
                // Load the record from database
                $queryBuilder = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
                    ->getQueryBuilderForTable('tt_content');

                $record = $queryBuilder
                    ->select('pi_flexform')
                    ->from('tt_content')
                    ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($editUid, \PDO::PARAM_INT)))
                    ->executeQuery()
                    ->fetchAssociative();

                if ($record && !empty($record['pi_flexform'])) {
                    $flexFormData = \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array($record['pi_flexform']);
                    if ($flexFormData && isset($flexFormData['data']['Common']['lDEF']["settings.$fieldName"]['vDEF'])) {
                        $values = $flexFormData['data']['Common']['lDEF']["settings.$fieldName"]['vDEF'];
                        if (is_string($values) && !empty($values)) {
                            $uuids = explode(',', $values);
                        }
                    }
                }
            }
        }

        return array_filter(array_unique($uuids));
    }

    /**
     * OLD N+1 METHODS REMOVED
     *
     * The following methods were removed because they caused N+1 query problems:
     * - fetchOrganizationByUuid() - replaced by bulk query
     * - fetchPersonByUuid() - replaced by bulk query
     * - fetchProjectByUuid() - replaced by bulk query
     *
     * Use getSelectedItemsWithRealNames() instead, which fetches all items in a single bulk query.
     */

    /**
     * Get selected items with their real names from API (BULK QUERY - NO N+1!)
     *
     * Fetches ALL selected items in a SINGLE API call instead of N separate calls.
     * This fixes the major performance bottleneck in backend form loading.
     *
     * BEFORE: 30 selected persons = 30 API calls (N+1 problem)
     * AFTER: 30 selected persons = 1 API call (bulk query)
     */
    protected function getSelectedItemsWithRealNames(array $uuids, string $type): array
    {
        $items = [];

        if (empty($uuids)) {
            return $items;
        }

        try {
            // ✅ SINGLE BULK API CALL - fetches ALL items at once
            $result = null;
            switch ($type) {
                case 'org':
                    $result = $this->apiService->getOrganisationalUnitsByUuids($uuids, [
                        'locale' => $this->locale,
                        'fields' => ['uuid', 'name.text.value']
                    ]);
                    break;

                case 'person':
                    $result = $this->apiService->getPersonsByUuids($uuids, [
                        'locale' => $this->locale,
                        'fields' => [
                            'uuid',
                            'name.*',
                            'honoraryStaffOrganisationAssociations.uuid',
                            'honoraryStaffOrganisationAssociations.period.*',
                            'honoraryStaffOrganisationAssociations.organisationalUnit.uuid',
                            'honoraryStaffOrganisationAssociations.organisationalUnit.name.*'
                        ],
                        'employmentStatus' => 'ACTIVE'
                    ]);
                    break;

                case 'project':
                    $result = $this->apiService->getProjectsByUuids($uuids, [
                        'locale' => $this->locale,
                        'fields' => ['uuid', 'acronym', 'title.*'],
                        'workflowSteps' => ['validated']
                    ]);
                    break;
            }

            // Process bulk result and build items array
            if ($result && isset($result['items'])) {
                // Create UUID-indexed map for fast lookup
                $itemsByUuid = [];
                foreach ($result['items'] as $item) {
                    $itemsByUuid[$item['uuid']] = $item;
                }

                // Build items array in the SAME ORDER as input UUIDs
                // This preserves the editor's selection order
                foreach ($uuids as $uuid) {
                    if (empty($uuid)) continue;

                    if (isset($itemsByUuid[$uuid])) {
                        $item = $itemsByUuid[$uuid];
                        $displayName = null;

                        switch ($type) {
                            case 'org':
                                $displayName = $this->extractLocalizedName($item['name'] ?? [], $this->locale);
                                break;

                            case 'person':
                                $personName = ($item['name']['lastName'] ?? '') . ', ' . ($item['name']['firstName'] ?? '');
                                $organizationNames = $this->getActiveOrganizationNames($item);
                                if (!empty($organizationNames)) {
                                    $displayName = $personName . ' (' . implode(', ', $organizationNames) . ')';
                                } else {
                                    $displayName = $personName;
                                }
                                break;

                            case 'project':
                                $displayName = $this->extractLocalizedName($item['title'] ?? [], $this->locale);
                                if (empty($displayName)) {
                                    $displayName = 'Unknown Project';
                                }
                                if (!empty($item['acronym']) && strpos($displayName, $item['acronym']) === false) {
                                    $displayName = $item['acronym'] . ' - ' . $displayName;
                                }
                                break;
                        }

                        if ($displayName) {
                            $items[] = [$displayName, $uuid];
                        } else {
                            // Fallback to placeholder
                            $placeholder = '[' . ucfirst($type) . ': ' . substr($uuid, 0, 8) . '...]';
                            $items[] = [$placeholder, $uuid];
                        }
                    } else {
                        // UUID not found in bulk result - use placeholder
                        $placeholder = '[' . ucfirst($type) . ': ' . substr($uuid, 0, 8) . '...]';
                        $items[] = [$placeholder, $uuid];
                    }
                }
            } else {
                // Bulk query failed - fall back to placeholders
                foreach ($uuids as $uuid) {
                    if (empty($uuid)) continue;
                    $placeholder = '[' . ucfirst($type) . ': ' . substr($uuid, 0, 8) . '...]';
                    $items[] = [$placeholder, $uuid];
                }
            }

        } catch (\Exception $e) {
            // Error during bulk query - fall back to placeholders
            error_log('[Pure Debug] Bulk query failed for ' . $type . ': ' . $e->getMessage());
            foreach ($uuids as $uuid) {
                if (empty($uuid)) continue;
                $placeholder = '[' . ucfirst($type) . ': ' . substr($uuid, 0, 8) . '...]';
                $items[] = [$placeholder, $uuid];
            }
        }

        return $items;
    }
}
