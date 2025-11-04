<?php
declare(strict_types=1);

namespace Univie\UniviePure\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use Univie\UniviePure\Service\ApiServiceFactory;
use Univie\UniviePure\Service\ApiServiceInterface;

/**
 * AJAX Controller for dynamic loading of Pure data in backend forms
 * Following TYPO3 core patterns for backend AJAX controllers
 */
class AjaxController
{
    protected ApiServiceInterface $apiService;

    private const MIN_SEARCH_LENGTH = 3;
    private const SEARCH_SIZE = 20;
    private const LOCALE_MAP = [
        'de' => 'de_DE',
        'en' => 'en_GB',
        'default' => 'de_DE'
    ];

    public function __construct(ApiServiceFactory $apiServiceFactory)
    {
        // Use configured API (respects PURE_API_VERSION environment variable)
        $this->apiService = $apiServiceFactory->create();
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

        // Use unified API interface (respects PURE_API_VERSION environment variable)
        $organisations = $this->apiService->getOrganisationalUnits([
            'searchString' => $searchTerm,
            'locale' => $locale,
            'size' => self::SEARCH_SIZE,
            'fields' => ['uuid', 'name.text.value'],
            'ordering' => 'name',
            'returnUsedContent' => 'true'
        ]);

        $results = [];

        if (is_array($organisations) && isset($organisations['items'])) {
            foreach ($organisations['items'] as $org) {
                // Get the best available name for current locale
                $label = $this->extractLocalizedName($org['name'] ?? [], $locale);

                // Skip if no label found
                if (empty($label)) {
                    continue;
                }

                $results[] = [
                    'value' => $org['uuid'],
                    'label' => $label
                ];
            }
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

        // Use unified API interface (respects PURE_API_VERSION environment variable)
        $persons = $this->apiService->getPersons([
            'searchString' => $searchTerm,
            'locale' => $locale,
            'size' => self::SEARCH_SIZE,
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

                $results[] = [
                    'value' => $person['uuid'],
                    'label' => $displayName
                ];
            }
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

        // Use unified API interface (respects PURE_API_VERSION environment variable)
        $projects = $this->apiService->getProjects([
            'searchString' => $searchTerm,
            'locale' => $locale,
            'size' => self::SEARCH_SIZE,
            'fields' => ['uuid', 'title.*', 'acronym'],
            'ordering' => 'title',
            'workflowSteps' => ['validated']
        ]);

        $results = [];

        if (is_array($projects) && isset($projects['items'])) {
            foreach ($projects['items'] as $project) {
                // Get the best available title for current locale
                $title = $this->extractLocalizedName($project['title'] ?? [], $locale);

                if (empty($title)) {
                    $title = 'Unknown Project';
                }

                // Add acronym if available and not already in title
                if (!empty($project['acronym']) && strpos($title, $project['acronym']) === false) {
                    $title = $project['acronym'] . ' - ' . $title;
                }

                $results[] = [
                    'value' => $project['uuid'],
                    'label' => $title
                ];
            }
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