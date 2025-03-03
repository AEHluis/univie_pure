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
    private const CACHE_LIFETIME = 21600; // 6 hours in seconds

    private string $locale;
    private FrontendInterface $cache;
    private WebService $webService;

    public function __construct(WebService $webService = null)
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
        $postData = trim('<?xml version="1.0"?>
            <organisationalUnitsQuery>
            <size>999999</size>
            <locales>
            <locale>' . $this->locale . '</locale>
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

        if (is_array($organisations) && isset($organisations['items'])) {
            foreach ($organisations['items'] as $org) {
                $config['items'][] = [
                    $org['name']['text']['0']['value'],
                    $org['uuid']
                ];
            }
        }
    }

    public function getPersons(&$config): void
    {
        $personXML = trim('<?xml version="1.0"?>
            <personsQuery>
            <size>999999</size>
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

        if (is_array($persons) && isset($persons['items'])) {
            foreach ($persons['items'] as $person) {
                $config['items'][] = [
                    $person['name']['lastName'] . ', ' . $person['name']['firstName'],
                    $person['uuid']
                ];
            }
        }
    }

    public function getProjects(&$config): void
    {
        $projectsXML = trim('<?xml version="1.0"?>
            <projectsQuery>
            <size>999999</size>
            <locales>
            <locale>' . $this->locale . '</locale>
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
            array_map(function($parent) use ($unsorted) {
                if (($parent['disabled'] ?? false) || !$this->classificationHasChild($parent)) {
                    return null;
                }

                $children = [];
                if (isset($parent['classificationRelations'])) {
                    $children = array_values(array_filter(
                        array_map(function($relation) use ($unsorted) {
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
            <searchString>' . htmlspecialchars($email) . '</searchString>
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
        string $message,
        string $title,
        ContextualFeedbackSeverity $severity
    ): void {
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
}