<?php

declare(strict_types=1);

namespace Univie\UniviePure\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Service\ApiServiceInterface;

/**
 * AJAX Controller for dynamic loading of Pure data in backend forms
 * Uses OpenAPI for all Pure API operations.
 */
class AjaxController
{
    private const MIN_SEARCH_LENGTH = 3;
    private const SEARCH_SIZE = 50;
    private const MIN_RELEVANCE_SCORE = 50;
    private const LOCALE_MAP = [
        'de' => 'de_DE',
        'en' => 'en_GB',
        'default' => 'de_DE'
    ];

    public function __construct(
        private readonly ApiServiceInterface $apiService
    ) {}

    /**
     * Get the current backend user's locale
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

        try {
            $response = $this->apiService->getOrganisationalUnits([
                'search' => $searchTerm,
                'limit' => self::SEARCH_SIZE,
                'locale' => $locale,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['results' => [], 'error' => $e->getMessage()]);
        }

        $results = [];
        $items = $response['items'] ?? [];

        foreach ($items as $org) {
            $label = $this->extractLocalizedName($org['name'] ?? [], $locale);

            if (empty($label)) {
                continue;
            }

            $score = $this->calculateRelevanceScore($searchTerm, $label, $label);

            if ($score >= self::MIN_RELEVANCE_SCORE) {
                $results[] = [
                    'value' => $org['uuid'] ?? '',
                    'label' => $label,
                    'score' => $score
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] - $a['score']);
        $results = array_map(fn($item) => ['value' => $item['value'], 'label' => $item['label']], $results);
        $results = array_slice($results, 0, 20);

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

        try {
            $response = $this->apiService->getPersons([
                'search' => $searchTerm,
                'limit' => self::SEARCH_SIZE,
                'locale' => $locale,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['results' => [], 'error' => $e->getMessage()]);
        }

        $results = [];
        $items = $response['items'] ?? [];

        foreach ($items as $person) {
            $lastName = $person['name']['lastName'] ?? '';
            $firstName = $person['name']['firstName'] ?? '';
            $personName = $lastName . ', ' . $firstName;

            $organizationNames = $this->getActiveOrganizationNames($person, $locale);

            if (!empty($organizationNames)) {
                $displayName = $personName . ' (' . implode(', ', $organizationNames) . ')';
            } else {
                $displayName = $personName;
            }

            $score = $this->calculateRelevanceScore($searchTerm, $personName, $displayName);

            if ($score >= self::MIN_RELEVANCE_SCORE) {
                $results[] = [
                    'value' => $person['uuid'] ?? '',
                    'label' => $displayName,
                    'score' => $score
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] - $a['score']);
        $results = array_map(fn($item) => ['value' => $item['value'], 'label' => $item['label']], $results);
        $results = array_slice($results, 0, 20);

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

        try {
            $response = $this->apiService->getProjects([
                'search' => $searchTerm,
                'limit' => self::SEARCH_SIZE,
                'locale' => $locale,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['results' => [], 'error' => $e->getMessage()]);
        }

        $results = [];
        $items = $response['items'] ?? [];

        foreach ($items as $project) {
            $title = $this->extractLocalizedName($project['title'] ?? [], $locale);

            if (empty($title)) {
                $title = 'Unknown Project';
            }

            $originalTitle = $title;

            if (!empty($project['acronym']) && strpos($title, $project['acronym']) === false) {
                $title = $project['acronym'] . ' - ' . $title;
            }

            $scoreByTitle = $this->calculateRelevanceScore($searchTerm, $originalTitle, $title);
            $scoreByAcronym = !empty($project['acronym'])
                ? $this->calculateRelevanceScore($searchTerm, $project['acronym'], $title)
                : 0;
            $score = max($scoreByTitle, $scoreByAcronym);

            if ($score >= self::MIN_RELEVANCE_SCORE) {
                $results[] = [
                    'value' => $project['uuid'] ?? '',
                    'label' => $title,
                    'score' => $score
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] - $a['score']);
        $results = array_map(fn($item) => ['value' => $item['value'], 'label' => $item['label']], $results);
        $results = array_slice($results, 0, 20);

        return new JsonResponse(['results' => $results]);
    }

    /**
     * Search equipments via AJAX
     */
    public function searchEquipmentsAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $searchTerm = trim($parsedBody['searchTerm'] ?? '');

        if (strlen($searchTerm) < self::MIN_SEARCH_LENGTH) {
            return new JsonResponse(['results' => []]);
        }

        $locale = $this->getBackendUserLocale();

        try {
            $response = $this->apiService->getEquipments([
                'search' => $searchTerm,
                'limit' => self::SEARCH_SIZE,
                'locale' => $locale,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['results' => [], 'error' => $e->getMessage()]);
        }

        $results = [];
        $items = $response['items'] ?? [];

        foreach ($items as $equipment) {
            $label = $this->getEquipmentLabel($equipment, $locale);

            if (empty($label)) {
                $label = 'Unknown Equipment';
            }

            $uuid = $equipment['uuid'] ?? '';
            if (empty($uuid)) {
                continue;
            }

            $score = $this->calculateRelevanceScore($searchTerm, $label, $label);

            if ($score >= 10) {
                $results[] = [
                    'value' => $uuid,
                    'label' => $label,
                    'score' => $score
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] - $a['score']);
        $results = array_map(fn($item) => ['value' => $item['value'], 'label' => $item['label']], $results);
        $results = array_slice($results, 0, 20);

        return new JsonResponse(['results' => $results]);
    }

    /**
     * Extract active organization names from person data
     */
    private function getActiveOrganizationNames(array $person, string $locale): array
    {
        $organizationNames = [];

        // Try different association field names (OpenAPI structure)
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
                $orgName = $this->extractLocalizedName($orgUnit['name'], $locale);

                if (!empty($orgName) && !in_array($orgName, $organizationNames)) {
                    $organizationNames[] = $orgName;
                }
            }
        }

        return $organizationNames;
    }

    /**
     * Calculate relevance score for search results
     */
    private function calculateRelevanceScore(string $searchTerm, string $primaryField, string $label): int
    {
        $searchTermLower = mb_strtolower($searchTerm);
        $primaryFieldLower = mb_strtolower($primaryField);
        $labelLower = mb_strtolower($label);

        if ($primaryFieldLower === $searchTermLower) {
            return 100;
        }

        if (mb_strpos($primaryFieldLower, $searchTermLower) === 0) {
            return 90;
        }

        if (preg_match('/\b' . preg_quote($searchTermLower, '/') . '\b/ui', $primaryFieldLower)) {
            return 80;
        }

        if (mb_strpos($primaryFieldLower, $searchTermLower) !== false) {
            return 70;
        }

        if (mb_strpos($labelLower, $searchTermLower) !== false) {
            return 55;
        }

        return 10;
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
        if (isset($texts[$locale]) && is_string($texts[$locale])) {
            return $texts[$locale];
        }
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
     * Resolve equipment label from API response
     */
    private function getEquipmentLabel(array $equipment, string $locale): string
    {
        $label = $this->extractLocalizedName($equipment['title'] ?? [], $locale);
        if ($label !== '') {
            return $label;
        }

        $label = $this->extractLocalizedName($equipment['name'] ?? [], $locale);
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
}
