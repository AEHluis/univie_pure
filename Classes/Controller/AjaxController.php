<?php
declare(strict_types=1);

namespace Univie\UniviePure\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use Univie\UniviePure\Service\WebService;

/**
 * AJAX Controller for dynamic loading of Pure data in backend forms
 * Following TYPO3 core patterns for backend AJAX controllers
 */
class AjaxController
{
    protected WebService $webService;
    
    private const MIN_SEARCH_LENGTH = 3;
    private const SEARCH_SIZE = 50;
    private const MIN_RELEVANCE_SCORE = 50; // Only show results with score >= 50
    private const LOCALE_MAP = [
        'de' => 'de_DE',
        'en' => 'en_GB',
        'default' => 'de_DE'
    ];

    public function __construct()
    {
        $this->webService = GeneralUtility::makeInstance(WebService::class);
    }
    
    /**
     * Get the current backend user's locale
     * Maps TYPO3 backend language to Pure API locale format
     */
    protected function getBackendUserLocale(): string
    {
        try {
            $languageServiceFactory = GeneralUtility::makeInstance(LanguageServiceFactory::class);
            $languageService = $languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
            $lang = $languageService->lang ?? 'de';
        } catch (\Exception $e) {
            $lang = 'de';
        }
        
        return self::LOCALE_MAP[$lang] ?? self::LOCALE_MAP['default'];
    }
    
    /**
     * Build XML query for Pure API
     */
    protected function buildXmlQuery(string $queryType, string $searchTerm, string $locale, array $fields, array $additionalElements = []): string
    {
        $xml = '<?xml version="1.0"?>';
        $xml .= '<' . $queryType . '>';
        $xml .= '<size>' . self::SEARCH_SIZE . '</size>';
        $xml .= '<offset>0</offset>';
        $xml .= '<locales><locale>' . htmlspecialchars($locale, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</locale></locales>';
        
        // Add fields
        $xml .= '<fields>';
        foreach ($fields as $field) {
            $xml .= '<field>' . htmlspecialchars($field, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</field>';
        }
        $xml .= '</fields>';
        
        // Add additional elements (ordering, filters, etc.)
        foreach ($additionalElements as $key => $value) {
            if (is_array($value)) {
                $xml .= '<' . $key . '>';
                foreach ($value as $item) {
                    $xml .= '<' . rtrim($key, 's') . '>' . htmlspecialchars($item, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</' . rtrim($key, 's') . '>';
                }
                $xml .= '</' . $key . '>';
            } else {
                $xml .= '<' . $key . '>' . htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</' . $key . '>';
            }
        }
        
        $xml .= '<searchString>' . htmlspecialchars($searchTerm, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>';
        $xml .= '</' . $queryType . '>';
        
        return trim($xml);
    }

    /**
     * Search organizations via AJAX
     */
    public function searchOrganizationsAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $searchTerm = trim($parsedBody['searchTerm'] ?? '');
        
        if (strlen($searchTerm) < self::MIN_SEARCH_LENGTH) {
            return new JsonResponse(['results' => []]);
        }

        $locale = $this->getBackendUserLocale();
        
        $postData = $this->buildXmlQuery(
            'organisationalUnitsQuery',
            $searchTerm,
            $locale,
            ['uuid', 'name.text.value'],
            [
                'orderings' => ['name'],
                'returnUsedContent' => 'true'
            ]
        );

        $organisations = $this->webService->getJson('organisational-units', $postData);

        $results = [];

        if (is_array($organisations) && isset($organisations['items'])) {
            foreach ($organisations['items'] as $org) {
                // Get the best available name for current locale
                $label = $this->extractLocalizedName($org['name'] ?? [], $locale);

                // Skip if no label found
                if (empty($label)) {
                    continue;
                }

                // Calculate relevance score to prioritize name matches
                $score = $this->calculateRelevanceScore($searchTerm, $label, $label);

                // Only include results above minimum relevance threshold
                if ($score >= self::MIN_RELEVANCE_SCORE) {
                    $results[] = [
                        'value' => $org['uuid'],
                        'label' => $label,
                        'score' => $score
                    ];
                }
            }

            // Sort by relevance score (highest first)
            usort($results, function($a, $b) {
                return $b['score'] - $a['score'];
            });

            // Remove score from output (only used for sorting)
            $results = array_map(function($item) {
                return ['value' => $item['value'], 'label' => $item['label']];
            }, $results);

            // Limit to top 20 results after filtering
            $results = array_slice($results, 0, 20);
        }

        return new JsonResponse(['results' => $results]);
    }


    /**
     * Search persons with organization via AJAX
     */
    public function searchPersonsWithOrganizationAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $searchTerm = trim($parsedBody['searchTerm'] ?? '');
        
        if (strlen($searchTerm) < self::MIN_SEARCH_LENGTH) {
            return new JsonResponse(['results' => []]);
        }

        $locale = $this->getBackendUserLocale();
        
        $postData = $this->buildXmlQuery(
            'personsQuery',
            $searchTerm,
            $locale,
            [
                'uuid',
                'name.*',
                'honoraryStaffOrganisationAssociations.uuid',
                'honoraryStaffOrganisationAssociations.period.*',
                'honoraryStaffOrganisationAssociations.organisationalUnit.uuid',
                'honoraryStaffOrganisationAssociations.organisationalUnit.name.*'
            ],
            [
                'orderings' => ['lastName'],
                'employmentStatus' => 'ACTIVE'
            ]
        );

        $persons = $this->webService->getJson('persons', $postData);
        $results = [];

        if (is_array($persons) && isset($persons['items'])) {
            foreach ($persons['items'] as $person) {
                $personName = $person['name']['lastName'] . ', ' . $person['name']['firstName'];
                $organizationNames = $this->getActiveOrganizationNames($person, $locale);

                if (!empty($organizationNames)) {
                    $displayName = $personName . ' (' . implode(', ', $organizationNames) . ')';
                } else {
                    $displayName = $personName;
                }

                // Calculate relevance score to prioritize name matches
                $score = $this->calculateRelevanceScore($searchTerm, $personName, $displayName);

                // Only include results above minimum relevance threshold
                if ($score >= self::MIN_RELEVANCE_SCORE) {
                    $results[] = [
                        'value' => $person['uuid'],
                        'label' => $displayName,
                        'score' => $score
                    ];
                }
            }

            // Sort by relevance score (highest first)
            usort($results, function($a, $b) {
                return $b['score'] - $a['score'];
            });

            // Remove score from output (only used for sorting)
            $results = array_map(function($item) {
                return ['value' => $item['value'], 'label' => $item['label']];
            }, $results);

            // Limit to top 20 results after filtering
            $results = array_slice($results, 0, 20);
        }

        return new JsonResponse(['results' => $results]);
    }

    /**
     * Search projects via AJAX
     */
    public function searchProjectsAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $searchTerm = trim($parsedBody['searchTerm'] ?? '');
        
        if (strlen($searchTerm) < self::MIN_SEARCH_LENGTH) {
            return new JsonResponse(['results' => []]);
        }

        $locale = $this->getBackendUserLocale();
        
        $postData = $this->buildXmlQuery(
            'projectsQuery',
            $searchTerm,
            $locale,
            ['uuid', 'title.*', 'acronym'],
            [
                'orderings' => ['title'],
                'workflowSteps' => ['validated']
            ]
        );

        $projects = $this->webService->getJson('projects', $postData);
        $results = [];

        if (is_array($projects) && isset($projects['items'])) {
            foreach ($projects['items'] as $project) {
                // Get the best available title for current locale
                $title = $this->extractLocalizedName($project['title'] ?? [], $locale);

                if (empty($title)) {
                    $title = 'Unknown Project';
                }

                // Store original title for scoring
                $originalTitle = $title;

                // Add acronym if available and not already in title
                if (!empty($project['acronym']) && strpos($title, $project['acronym']) === false) {
                    $title = $project['acronym'] . ' - ' . $title;
                }

                // Calculate relevance score (check both title and acronym)
                $scoreByTitle = $this->calculateRelevanceScore($searchTerm, $originalTitle, $title);
                $scoreByAcronym = !empty($project['acronym']) ?
                    $this->calculateRelevanceScore($searchTerm, $project['acronym'], $title) : 0;
                $score = max($scoreByTitle, $scoreByAcronym);

                // Only include results above minimum relevance threshold
                if ($score >= self::MIN_RELEVANCE_SCORE) {
                    $results[] = [
                        'value' => $project['uuid'],
                        'label' => $title,
                        'score' => $score
                    ];
                }
            }

            // Sort by relevance score (highest first)
            usort($results, function($a, $b) {
                return $b['score'] - $a['score'];
            });

            // Remove score from output (only used for sorting)
            $results = array_map(function($item) {
                return ['value' => $item['value'], 'label' => $item['label']];
            }, $results);

            // Limit to top 20 results after filtering
            $results = array_slice($results, 0, 20);
        }

        return new JsonResponse(['results' => $results]);
    }

    /**
     * Extract active organization names from person data
     */
    private function getActiveOrganizationNames(array $person, string $locale): array
    {
        $organizationNames = [];
        
        if (!isset($person['honoraryStaffOrganisationAssociations'])) {
            return $organizationNames;
        }
        
        $associations = $person['honoraryStaffOrganisationAssociations'];
        
        // Handle single association
        if (isset($associations['organisationalUnit'])) {
            $associations = [$associations];
        }
        
        foreach ($associations as $association) {
            // Check if association is currently active
            if (isset($association['period']['endDate']) && 
                !empty($association['period']['endDate']) &&
                strtotime($association['period']['endDate']) < time()) {
                continue; // Skip inactive associations
            }
            
            if (isset($association['organisationalUnit']['name'])) {
                // Use the localized name extraction
                $orgName = $this->extractLocalizedName($association['organisationalUnit']['name'], $locale);
                
                if (!empty($orgName) && !in_array($orgName, $organizationNames)) {
                    $organizationNames[] = $orgName;
                }
            }
        }
        
        return $organizationNames;
    }
    
    /**
     * Calculate relevance score for search results
     * Prioritizes matches in name/title fields over matches in other fields
     *
     * @param string $searchTerm The search term entered by user
     * @param string $primaryField The primary field to check (name, title, etc.)
     * @param string $label The full display label
     * @return int Relevance score (higher = more relevant)
     */
    private function calculateRelevanceScore(string $searchTerm, string $primaryField, string $label): int
    {
        $searchTermLower = mb_strtolower($searchTerm);
        $primaryFieldLower = mb_strtolower($primaryField);
        $labelLower = mb_strtolower($label);

        // Exact match in primary field = highest score
        if ($primaryFieldLower === $searchTermLower) {
            return 100;
        }

        // Starts with search term in primary field = very high score
        if (mb_strpos($primaryFieldLower, $searchTermLower) === 0) {
            return 90;
        }

        // Contains search term as word in primary field = high score
        // Check if search term appears as a word boundary (e.g., "Lee" in "Lee, John" but not in "Sleeper")
        if (preg_match('/\b' . preg_quote($searchTermLower, '/') . '\b/ui', $primaryFieldLower)) {
            return 80;
        }

        // Contains search term anywhere in primary field = medium score
        if (mb_strpos($primaryFieldLower, $searchTermLower) !== false) {
            return 70;
        }

        // Contains search term in full label (e.g., in organization name) = lower score
        if (mb_strpos($labelLower, $searchTermLower) !== false) {
            return 55;
        }

        // Found by API but not in visible fields = very low score (likely in bio, etc.)
        // This will be filtered out by MIN_RELEVANCE_SCORE = 50
        return 10;
    }

    /**
     * Extract localized name/title from Pure API response structure
     *
     * @param array $nameData The name data from API (could be name or title field)
     * @param string $locale The desired locale (e.g., 'de_DE' or 'en_GB')
     * @return string The localized name or fallback to first available
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
}