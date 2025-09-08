<?php
declare(strict_types=1);

namespace Univie\UniviePure\Utility;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Service\WebService;
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
    private const CACHE_LIFETIME = 86400; // 24 hours

    private string $locale;
    private FrontendInterface $cache;
    private WebService $webService;

    public function __construct(?WebService $webService = null)
    {
        $this->locale = LanguageUtility::getBackendLanguage();
        $this->cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('univie_pure');
        $this->webService = $webService ?? GeneralUtility::makeInstance(WebService::class);
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
        
        // For AJAX dynamic loading, only load minimal items initially
        $postData = trim('<?xml version="1.0"?>
            <organisationalUnitsQuery>
            <size>8</size>
            <locales>
            <locale>' . htmlspecialchars($this->locale, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</locale>
            </locales>
            <fields>
            <field>uuid</field>
            <field>name.text.value</field>
            </fields>
            <orderings>
            <ordering>name</ordering>
            </orderings>
            <returnUsedContent>true</returnUsedContent>
            </organisationalUnitsQuery>');

        $organisations = $this->getOrganisationsFromCache($this->locale);
        if ($organisations === null || !$this->isValidOrganisationsData($organisations)) {
            $organisations = $this->webService->getJson('organisational-units', $postData);

            if (!$organisations || !isset($organisations['items'])) {
                $this->addFlashMessage(
                    'Could not fetch organisations from the API. Please check your connection.',
                    'Organisations Fetch Failed',
                    ContextualFeedbackSeverity::WARNING
                );
                return;
            }

            $this->storeOrganisationsToCache($organisations, $this->locale);
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
                    $name = $org['name']['text']['0']['value'];
                    $config['items'][] = [$name, $org['uuid']];
                    
                    // Cache the name for future use
                    $this->setCachedItemName($org['uuid'], 'org', $name);
                }
            }
        }
        
        // Add hint for users to search
        $searchHint = $this->locale === 'de_DE' ? 
            '→ Suchen für mehr...' : 
            '→ Search for more...';
        $config['items'][] = ['─────────────────────', '--div--'];
        $config['items'][] = [$searchHint, ''];
    }

    public function getPersons(&$config): void
    {
        // Always load ALL selected items to ensure they remain available
        $selectedUuids = $this->getCurrentlySelectedUuids('selectorPersons');
        
        // Fetch real names for selected items
        $selectedItems = [];
        if (!empty($selectedUuids)) {
            $selectedItems = $this->getSelectedItemsWithRealNames($selectedUuids, 'person');
        }
        
        // For AJAX dynamic loading, only load minimal items initially  
        $personXML = trim('<?xml version="1.0"?>
            <personsQuery>
            <size>5</size>
            <fields>
            <field>uuid</field>
            <field>name.*</field>
            </fields>
            <orderings>
            <ordering>lastName</ordering>
            </orderings>
            <employmentStatus>ACTIVE</employmentStatus>
            </personsQuery>');

        $persons = $this->getPersonsFromCache();

        if ($persons === null || !$this->isValidPersonsData($persons)) {
            $persons = $this->webService->getJson('persons', $personXML);
            if (!$persons || !isset($persons['items'])) {
                $this->addFlashMessage(
                    'Could not fetch Persondata from the API. Please check your connection.',
                    'Persondata Fetch Failed',
                    ContextualFeedbackSeverity::WARNING
                );
                return;
            }
            $this->storePersonsToCache($persons);
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
                    $name = $person['name']['lastName'] . ', ' . $person['name']['firstName'];
                    $config['items'][] = [$name, $person['uuid']];
                    
                    // Cache the name for future use
                    $this->setCachedItemName($person['uuid'], 'person', $name);
                }
            }
        }
        
        // Add hint for users to search  
        $searchHint = $this->locale === 'de_DE' ?
            '→ Suchen für mehr...' :
            '→ Search for more...';
        $config['items'][] = ['─────────────────────', '--div--'];
        $config['items'][] = [$searchHint, ''];
    }

    public function getPersonsByOrganization(&$config): void
    {
        // Always load ALL selected items to ensure they remain available
        $selectedUuids = $this->getCurrentlySelectedUuids('selectorPersonsWithOrganization');
        
        // Fetch real names for selected items
        $selectedItems = [];
        if (!empty($selectedUuids)) {
            $selectedItems = $this->getSelectedItemsWithRealNames($selectedUuids, 'person');
        }
        
        // For AJAX dynamic loading, only load minimal items initially
        $personXML = trim('<?xml version="1.0"?>
            <personsQuery>
            <size>5</size>
            <fields>
            <field>uuid</field>
            <field>name.*</field>
            <field>honoraryStaffOrganisationAssociations.uuid</field>
            <field>honoraryStaffOrganisationAssociations.period.*</field>
            <field>honoraryStaffOrganisationAssociations.organisationalUnit.uuid</field>
            <field>honoraryStaffOrganisationAssociations.organisationalUnit.name.*</field>
            </fields>
            <orderings>
            <ordering>lastName</ordering>
            </orderings>
            <employmentStatus>ACTIVE</employmentStatus>
            </personsQuery>');

        $persons = $this->getPersonsByOrganizationFromCache();

        if ($persons === null || !$this->isValidPersonsData($persons)) {
            $persons = $this->webService->getJson('persons', $personXML);
            if (!$persons || !isset($persons['items'])) {
                $this->addFlashMessage(
                    'Could not fetch Person data with organization associations from the API. Please check your connection.',
                    'Person Organization Data Fetch Failed',
                    ContextualFeedbackSeverity::WARNING
                );
                return;
            }
            $this->storePersonsByOrganizationToCache($persons);
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
                    
                    // Cache the name for future use
                    $this->setCachedItemName($person['uuid'], 'person', $displayName);
                }
            }
        }
        
        // Add hint for users to search
        $searchHint = $this->locale === 'de_DE' ?
            '→ Suchen für mehr...' :
            '→ Search for more...';
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
                
                if ($isActive && isset($association['organisationalUnit']['name']['text'])) {
                    // TYPO3 Backend language detection
                    $locale = ($GLOBALS['BE_USER']->uc['lang'] == 'de') ? 'de_DE' : 'en_GB';
                    
                    // Find organizational unit name in appropriate language
                    $orgName = $association['organisationalUnit']['name']['text'][0]['value']; // fallback
                    foreach ($association['organisationalUnit']['name']['text'] as $text) {
                        if (isset($text['locale']) && $text['locale'] === $locale) {
                            $orgName = $text['value'];
                            break;
                        }
                    }
                    
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
        
        // For AJAX dynamic loading, only load minimal items initially
        $projectsXML = trim('<?xml version="1.0"?>
            <projectsQuery>
            <size>5</size>
            <locales>
            <locale>' . htmlspecialchars($this->locale, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</locale>
            </locales>
            <fields>
            <field>uuid</field>
            <field>acronym</field>
            <field>title.*</field>
            </fields>
            <orderings>
            <ordering>title</ordering>
            </orderings>
            <workflowSteps>
            <workflowStep>validated</workflowStep>
            </workflowSteps>
            </projectsQuery>');

        $projects = $this->getProjectsFromCache($this->locale);

        if ($projects === null || !$this->isValidProjectsData($projects)) {
            $projects = $this->webService->getJson('projects', $projectsXML);
            $this->storeProjectsToCache($projects, $this->locale);
        }

        if (is_array($projects) && isset($projects['items'])) {
            foreach ($projects['items'] as $project) {
                $title = $project['title']['text'][0]['value'];
                if (!empty($project['acronym']) && strpos($title, $project['acronym']) === false) {
                    $title = $project['acronym'] . ' - ' . $title;
                }
                $config['items'][] = [$title, $project['uuid']];
            }
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
                    $title = $project['title']['text'][0]['value'];
                    if (!empty($project['acronym']) && strpos($title, $project['acronym']) === false) {
                        $title = $project['acronym'] . ' - ' . $title;
                    }
                    $config['items'][] = [$title, $project['uuid']];
                    
                    // Cache the name for future use
                    $this->setCachedItemName($project['uuid'], 'project', $title);
                }
            }
        }
        
        // Add hint for users to search
        $searchHint = $this->locale === 'de_DE' ?
            '→ Suchen für mehr...' :
            '→ Search for more...';
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
            <locale>' . $this->locale . '</locale>
            </locales>
            <returnUsedContent>true</returnUsedContent>
            <navigationLink>true</navigationLink> 
            <baseUri>' . self::RESEARCHOUTPUT . '</baseUri>
            </classificationSchemesQuery>');

        $publicationTypes = $this->getTypesFromPublicationsFromCache();

        if ($publicationTypes === null || !$this->isValidPublicationTypesData($publicationTypes)) {
            $publicationTypes = $this->webService->getJson('classification-schemes', $classificationXML);
            $this->storeTypesFromPublicationsToCache($publicationTypes);
        }

        if (is_array($publicationTypes)) {
            $sorted = $this->sortClassification($publicationTypes);
            $this->sorted2items($sorted, $config);
        }
    }

    public function getCacheIdentifier(string $key, string $locale = ''): string
    {
        return sha1($key . $locale);
    }

    public function getFromCache(string $identifier)
    {
        return $this->cache->has($identifier) ? $this->cache->get($identifier) : null;
    }

    public function setToCache(string $identifier, $data): void
    {
        $this->cache->set($identifier, $data, [], self::CACHE_LIFETIME);
    }

    public function getTypesFromPublicationsFromCache()
    {
        return $this->getFromCache($this->getCacheIdentifier('getTypesFromPublications'));
    }

    public function getOrganisationsFromCache(string $lang)
    {
        return $this->getFromCache($this->getCacheIdentifier('getOrganisations', $lang));
    }

    public function getPersonsFromCache()
    {
        return $this->getFromCache($this->getCacheIdentifier('getPersons'));
    }

    public function getPersonsByOrganizationFromCache()
    {
        return $this->getFromCache($this->getCacheIdentifier('getPersonsByOrganization'));
    }

    public function getProjectsFromCache(string $lang)
    {
        return $this->getFromCache($this->getCacheIdentifier('getProjects', $lang));
    }

    public function storeTypesFromPublicationsToCache($data): void
    {
        $this->setToCache($this->getCacheIdentifier('getTypesFromPublications'), $data);
    }

    public function storeOrganisationsToCache($data, string $locale): void
    {
        $this->setToCache($this->getCacheIdentifier('getOrganisations', $locale), $data);
    }

    public function storePersonsToCache($data): void
    {
        $this->setToCache($this->getCacheIdentifier('getPersons'), $data);
    }

    public function storePersonsByOrganizationToCache($data): void
    {
        $this->setToCache($this->getCacheIdentifier('getPersonsByOrganization'), $data);
    }

    public function storeProjectsToCache($data, string $locale): void
    {
        $this->setToCache($this->getCacheIdentifier('getProjects', $locale), $data);
    }

    public function isValidOrganisationsData($data): bool
    {
        return is_array($data) && isset($data['items']) && count($data['items']) >= 1;
    }

    public function isValidPersonsData($data): bool
    {
        return is_array($data) && isset($data['items']) && count($data['items']) >= 1;
    }

    public function isValidProjectsData($data): bool
    {
        return is_array($data) && isset($data['items']) && count($data['items']) >= 1;
    }

    public function isValidPublicationTypesData($data): bool
    {
        return is_array($data) && isset($data['items']) && count($data['items']) >= 3;
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

        $config['items'][] = [
            $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectBlank'),
            -1
        ];
        $config['items'][] = [
            $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByUnit'),
            0
        ];
        $config['items'][] = [
            $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByPerson'),
            1
        ];
        $config['items'][] = [
            $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByPersonWithOrganization'),
            3
        ];

        $settings = $config['flexParentDatabaseRow']['pi_flexform'];
        $whatToDisplay = $settings['data']['sDEF']['lDEF']['settings.what_to_display']['vDEF'][0] ?? '';

        if ($whatToDisplay === 'PUBLICATIONS' || $whatToDisplay === 'DATASETS') {
            $config['items'][] = [
                $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByProject'),
                2
            ];
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
        
        // Method 1: Check POST data (form submission)
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
        
        // Method 2: Check GET parameters (edit mode)
        if (empty($uuids) && !empty($_GET['edit']['tt_content'])) {
            $editUid = key($_GET['edit']['tt_content']);
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
     * Get cached display name for an item
     */
    protected function getCachedItemName(string $uuid, string $type): ?string
    {
        $cacheKey = "item_name_{$type}_{$uuid}";
        return $this->getFromCache($this->getCacheIdentifier($cacheKey));
    }

    /**
     * Cache display name for an item
     */
    protected function setCachedItemName(string $uuid, string $type, string $name): void
    {
        $cacheKey = "item_name_{$type}_{$uuid}";
        $this->setToCache($this->getCacheIdentifier($cacheKey), $name);
    }

    /**
     * Add selected items that are not already in the config
     */
    protected function addSelectedItemsToConfig(&$config, array $selectedItems, string $type): void
    {
        if (empty($selectedItems)) {
            return;
        }

        // Get existing item values to avoid duplicates
        $existingValues = [];
        foreach ($config['items'] as $item) {
            if (isset($item[1])) {
                $existingValues[] = $item[1];
            }
        }

        // Add missing selected items by fetching them from API
        $missingItems = array_diff($selectedItems, $existingValues);
        if (!empty($missingItems)) {
            $this->fetchMissingItems($config, $missingItems, $type);
        }
    }

    /**
     * Fetch missing selected items from API
     */
    protected function fetchMissingItems(&$config, array $uuids, string $type): void
    {
        foreach ($uuids as $uuid) {
            if (empty($uuid)) continue;
            
            try {
                // Fetch individual item by UUID
                switch ($type) {
                    case 'organisations':
                        $item = $this->fetchOrganizationByUuid($uuid);
                        if ($item) {
                            $config['items'][] = [$item['name'], $uuid];
                        }
                        break;
                    
                    case 'persons':
                        $item = $this->fetchPersonByUuid($uuid);
                        if ($item) {
                            $config['items'][] = [$item['name'], $uuid];
                        }
                        break;
                    
                    case 'projects':
                        $item = $this->fetchProjectByUuid($uuid);
                        if ($item) {
                            $config['items'][] = [$item['title'], $uuid];
                        }
                        break;
                }
            } catch (\Exception $e) {
                // If fetch fails, add placeholder
                $config['items'][] = ['[' . $uuid . ']', $uuid];
            }
        }
    }

    /**
     * Fetch organization by UUID using search
     */
    protected function fetchOrganizationByUuid(string $uuid): ?array
    {
        // Use search by UUID instead of uuids element (which may not be supported)
        $postData = trim('<?xml version="1.0"?>
            <organisationalUnitsQuery>
            <size>1</size>
            <locales>
            <locale>' . htmlspecialchars($this->locale, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</locale>
            </locales>
            <fields>
            <field>uuid</field>
            <field>name.text.value</field>
            </fields>
            <searchString>' . htmlspecialchars($uuid, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>
            </organisationalUnitsQuery>');

        $result = $this->webService->getJson('organisational-units', $postData);
        
        if (is_array($result) && isset($result['items'][0])) {
            return [
                'name' => $result['items'][0]['name']['text']['0']['value']
            ];
        }
        
        return null;
    }

    /**
     * Fetch person by UUID using search
     */
    protected function fetchPersonByUuid(string $uuid): ?array
    {
        $personXML = trim('<?xml version="1.0"?>
            <personsQuery>
            <size>1</size>
            <fields>
            <field>uuid</field>
            <field>name.*</field>
            <field>honoraryStaffOrganisationAssociations.uuid</field>
            <field>honoraryStaffOrganisationAssociations.period.*</field>
            <field>honoraryStaffOrganisationAssociations.organisationalUnit.uuid</field>
            <field>honoraryStaffOrganisationAssociations.organisationalUnit.name.*</field>
            </fields>
            <employmentStatus>ACTIVE</employmentStatus>
            <searchString>' . htmlspecialchars($uuid, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>
            </personsQuery>');

        $result = $this->webService->getJson('persons', $personXML);
        
        if (is_array($result) && isset($result['items'][0])) {
            $person = $result['items'][0];
            $personName = $person['name']['lastName'] . ', ' . $person['name']['firstName'];
            
            // Add organization names if available
            $organizationNames = $this->getActiveOrganizationNames($person);
            if (!empty($organizationNames)) {
                $personName .= ' (' . implode(', ', $organizationNames) . ')';
            }
            
            return ['name' => $personName];
        }
        
        return null;
    }

    /**
     * Fetch project by UUID using search
     */
    protected function fetchProjectByUuid(string $uuid): ?array
    {
        $projectsXML = trim('<?xml version="1.0"?>
            <projectsQuery>
            <size>1</size>
            <locales>
            <locale>' . htmlspecialchars($this->locale, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</locale>
            </locales>
            <fields>
            <field>uuid</field>
            <field>acronym</field>
            <field>title.*</field>
            </fields>
            <workflowSteps>
            <workflowStep>validated</workflowStep>
            </workflowSteps>
            <searchString>' . htmlspecialchars($uuid, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>
            </projectsQuery>');

        $result = $this->webService->getJson('projects', $projectsXML);
        
        if (is_array($result) && isset($result['items'][0])) {
            $project = $result['items'][0];
            $title = $project['title']['text'][0]['value'];
            if (!empty($project['acronym']) && strpos($title, $project['acronym']) === false) {
                $title = $project['acronym'] . ' - ' . $title;
            }
            
            return ['title' => $title];
        }
        
        return null;
    }

    /**
     * Get selected items with their real names from API
     */
    protected function getSelectedItemsWithRealNames(array $uuids, string $type): array
    {
        $items = [];
        
        // First try to get names from cache
        foreach ($uuids as $uuid) {
            if (empty($uuid)) continue;
            
            $cachedName = $this->getCachedItemName($uuid, $type);
            if ($cachedName) {
                $items[] = [$cachedName, $uuid];
                continue;
            }
            
            // If not cached, try to fetch from API
            try {
                $realName = null;
                switch ($type) {
                    case 'org':
                        $item = $this->fetchOrganizationByUuid($uuid);
                        if ($item) {
                            $realName = $item['name'];
                            $this->setCachedItemName($uuid, $type, $realName);
                        }
                        break;
                    
                    case 'person':
                        $item = $this->fetchPersonByUuid($uuid);
                        if ($item) {
                            $realName = $item['name'];
                            $this->setCachedItemName($uuid, $type, $realName);
                        }
                        break;
                    
                    case 'project':
                        $item = $this->fetchProjectByUuid($uuid);
                        if ($item) {
                            $realName = $item['title'];
                            $this->setCachedItemName($uuid, $type, $realName);
                        }
                        break;
                }
                
                if ($realName) {
                    $items[] = [$realName, $uuid];
                } else {
                    // Fall back to placeholder if API call fails
                    $placeholder = '[' . ucfirst($type) . ': ' . substr($uuid, 0, 8) . '...]';
                    $items[] = [$placeholder, $uuid];
                }
                
            } catch (\Exception $e) {
                // Fall back to placeholder if fetch fails
                $placeholder = '[' . ucfirst($type) . ': ' . substr($uuid, 0, 8) . '...]';
                $items[] = [$placeholder, $uuid];
            }
        }
        
        return $items;
    }
}
