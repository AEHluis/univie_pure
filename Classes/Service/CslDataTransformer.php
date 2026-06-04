<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

/**
 * UPDATED CslDataTransformer with correct field mappings
 *
 * Based on real API responses from Uni Hannover FIS (2026-06-02)
 *
 * CRITICAL FIXES:
 * - title is in title.value (not title directly)
 * - DOI is in electronicVersions array (not top-level identifiers)
 * - Publication year is in publicationStatuses array (find current=true)
 * - Journal name is in journalAssociation.title.title
 * - Contributors have nested name structure
 * - journalNumber is the issue number
 */
class CslDataTransformer
{
    /**
     * Pure publication type URI to CSL type mapping
     *
     * Based on actual URIs from Uni Hannover:
     * /dk/atira/pure/researchoutput/researchoutputtypes/...
     */
    private const TYPE_MAPPING = [
        // Journal contributions
        'contributiontojournal/article' => 'article-journal',
        'contributiontojournal/review' => 'review',
        'contributiontojournal/letter' => 'article-journal',
        'contributiontojournal/editorial' => 'article-journal',

        // Conference contributions
        'contributiontoconference/paper' => 'paper-conference',
        'contributiontoconference/abstract' => 'paper-conference',
        'contributiontoconference/poster' => 'paper-conference',

        // Books
        'book/book' => 'book',
        'book/anthology' => 'book',
        'book/chapter' => 'chapter',

        // Theses
        'thesis/doctoral' => 'thesis',
        'thesis/master' => 'thesis',
        'thesis/bachelor' => 'thesis',

        // Other
        'patent' => 'patent',
        'report' => 'report',
        'workingpaper' => 'manuscript',

        // Fallbacks
        'contributiontojournal' => 'article-journal',
        'contributiontoconference' => 'paper-conference',
        'other' => 'article',
    ];

    private string $preferredLocale = 'en_GB';

    public function setPreferredLocale(string $locale): void
    {
        $this->preferredLocale = $locale;
    }

    public function getPreferredLocale(): string
    {
        return $this->preferredLocale;
    }

    /**
     * Transform research output to CSL-JSON
     *
     * @param array $data Pure research output data from OpenAPI
     * @param string|null $locale Optional locale override
     * @return array CSL-JSON formatted data
     */
    public function transformResearchOutput(array $data, ?string $locale = null): array
    {
        $effectiveLocale = $locale ?? $this->preferredLocale;
        $csl = [];

        // Required fields
        $csl['id'] = $data['uuid'] ?? $data['pureId'] ?? uniqid('pure_');
        $csl['type'] = $this->mapPublicationType($data);

        // Title - CRITICAL FIX: title is in title.value
        if (isset($data['title']['value'])) {
            $csl['title'] = $data['title']['value'];
        } elseif (isset($data['title']) && is_string($data['title'])) {
            $csl['title'] = $data['title'];
        }

        // Authors/Contributors - UPDATED for real structure
        if (isset($data['contributors']) && is_array($data['contributors'])) {
            $csl['author'] = $this->transformContributors($data['contributors']);
        }

        // Publication date - CRITICAL FIX: from publicationStatuses array
        $csl = array_merge($csl, $this->transformPublicationDate($data));

        // Container (journal, book, etc.) - UPDATED paths
        $csl = array_merge($csl, $this->transformContainer($data, $effectiveLocale));

        // Identifiers - CRITICAL FIX: DOI in electronicVersions
        $csl = array_merge($csl, $this->transformIdentifiers($data));

        // Volume, Issue, Pages - UPDATED field names
        if (isset($data['volume'])) {
            $csl['volume'] = (string)$data['volume'];
        }

        if (isset($data['journalNumber'])) {
            $csl['issue'] = (string)$data['journalNumber'];
        }

        if (isset($data['pages'])) {
            $csl['page'] = (string)$data['pages'];
        }

        // Abstract - handle multilingual with HTML
        if (isset($data['abstract'])) {
            $abstract = $this->extractTextValue($data['abstract'], $effectiveLocale);
            // Strip HTML tags from abstract
            $csl['abstract'] = strip_tags($abstract);
        }

        // Keywords - from keywordGroups
        $keywords = $this->extractKeywords($data, $effectiveLocale);
        if (!empty($keywords)) {
            $csl['keyword'] = implode(', ', $keywords);
        }

        // Publisher - handle potential multilingual structure
        if (isset($data['publisher']['name'])) {
            $csl['publisher'] = $this->extractTextValue($data['publisher']['name'], $effectiveLocale);
        } elseif (isset($data['publisher']) && is_string($data['publisher'])) {
            $csl['publisher'] = $data['publisher'];
        }

        // Publisher place - handle potential multilingual structure
        if (isset($data['publisherLocation'])) {
            $csl['publisher-place'] = $this->extractTextValue($data['publisherLocation'], $effectiveLocale);
        }

        // Edition
        if (isset($data['edition'])) {
            $csl['edition'] = (string)$data['edition'];
        }

        // Language - from language object
        if (isset($data['language']['uri'])) {
            $csl['language'] = $this->extractLanguageCode($data['language']['uri']);
        }

        // URL - portalUrl is the public URL (ensure string)
        if (isset($data['portalUrl'])) {
            $csl['URL'] = is_string($data['portalUrl'])
                ? $data['portalUrl']
                : $this->extractTextValue($data['portalUrl'], $effectiveLocale);
        }

        // Number of pages
        if (isset($data['numberOfPages'])) {
            $csl['number-of-pages'] = (string)$data['numberOfPages'];
        }

        // SAFEGUARD: Ensure all scalar CSL values are strings (not arrays)
        // This prevents htmlspecialchars errors in citeproc-php
        return $this->sanitizeCslValues($csl);
    }

    /**
     * Ensure all CSL values are proper types (strings for scalars)
     *
     * Prevents errors like "htmlspecialchars(): Argument must be string, array given"
     *
     * @param array $csl CSL data array
     * @return array Sanitized CSL array
     */
    private function sanitizeCslValues(array $csl): array
    {
        // Fields that should be arrays with specific structure
        $arrayFields = ['author', 'editor', 'issued', 'accessed'];

        foreach ($csl as $key => $value) {
            // Skip null values
            if ($value === null) {
                unset($csl[$key]);
                continue;
            }

            // Skip fields that are supposed to be arrays
            if (in_array($key, $arrayFields, true)) {
                // But ensure nested values are also sanitized
                if ($key === 'author' || $key === 'editor') {
                    $csl[$key] = $this->sanitizeNameArray($value);
                }
                continue;
            }

            // Convert arrays to strings for scalar fields
            if (is_array($value)) {
                // Try to extract a string value
                if (isset($value['value']) && is_string($value['value'])) {
                    $csl[$key] = $value['value'];
                } elseif (isset($value[0]) && is_string($value[0])) {
                    $csl[$key] = $value[0];
                } else {
                    // Last resort: convert to JSON or first available string
                    $stringVal = $this->extractTextValue($value, $this->preferredLocale);
                    $csl[$key] = $stringVal !== '' ? $stringVal : '';
                }
            }

            // Ensure strings are actually strings (not objects)
            if (!is_string($csl[$key]) && !is_array($csl[$key]) && !is_int($csl[$key])) {
                $csl[$key] = (string)$csl[$key];
            }
        }

        return $csl;
    }

    /**
     * Sanitize author/editor name array
     *
     * Ensures all name parts are strings, not arrays
     *
     * @param array $names Array of name objects
     * @return array Sanitized name array
     */
    private function sanitizeNameArray(array $names): array
    {
        $sanitized = [];

        foreach ($names as $name) {
            if (!is_array($name)) {
                continue;
            }

            $cleanName = [];

            // Process each name part
            foreach (['family', 'given', 'suffix', 'dropping-particle', 'non-dropping-particle', 'literal'] as $part) {
                if (isset($name[$part])) {
                    if (is_string($name[$part])) {
                        $cleanName[$part] = $name[$part];
                    } elseif (is_array($name[$part])) {
                        // Extract string from array
                        $cleanName[$part] = $this->extractTextValue($name[$part], $this->preferredLocale);
                    } else {
                        $cleanName[$part] = (string)$name[$part];
                    }
                }
            }

            // Only add if we have at least a family name or literal name
            if (!empty($cleanName['family']) || !empty($cleanName['literal'])) {
                $sanitized[] = $cleanName;
            }
        }

        return $sanitized;
    }

    /**
     * Transform publication date from publicationStatuses array
     *
     * CRITICAL: Pure stores multiple publication statuses (submitted, accepted, published)
     * We need to find the one with current=true
     *
     * @param array $data Research output data
     * @return array CSL date fields
     */
    private function transformPublicationDate(array $data): array
    {
        $csl = [];

        // Find current publication status
        $currentStatus = null;
        if (isset($data['publicationStatuses']) && is_array($data['publicationStatuses'])) {
            foreach ($data['publicationStatuses'] as $status) {
                if ($status['current'] ?? false) {
                    $currentStatus = $status;
                    break;
                }
            }

            // If no current status, try to find published status
            if (!$currentStatus) {
                foreach ($data['publicationStatuses'] as $status) {
                    $uri = $status['publicationStatus']['uri'] ?? '';
                    if (str_contains($uri, '/published')) {
                        $currentStatus = $status;
                        break;
                    }
                }
            }

            // Fallback to first status
            if (!$currentStatus && !empty($data['publicationStatuses'])) {
                $currentStatus = $data['publicationStatuses'][0];
            }
        }

        // Extract date from current status
        if ($currentStatus && isset($currentStatus['publicationDate'])) {
            $date = $currentStatus['publicationDate'];

            if (isset($date['year'])) {
                // Build date-parts array, only including non-null values
                // CSL expects: [[year]] or [[year, month]] or [[year, month, day]]
                $dateParts = [(int)$date['year']];
                if (isset($date['month']) && $date['month'] !== null) {
                    $dateParts[] = (int)$date['month'];
                    if (isset($date['day']) && $date['day'] !== null) {
                        $dateParts[] = (int)$date['day'];
                    }
                }
                $csl['issued'] = ['date-parts' => [$dateParts]];
            }
        } elseif (isset($data['submissionYear'])) {
            // Fallback to submission year
            $csl['issued'] = [
                'date-parts' => [[(int)$data['submissionYear']]]
            ];
        }

        return $csl;
    }

    /**
     * Transform contributors to CSL author format
     *
     * UPDATED: Real structure has nested name object
     *
     * @param array $contributors Contributors array from Pure
     * @return array CSL author array
     */
    private function transformContributors(array $contributors): array
    {
        $authors = [];

        foreach ($contributors as $contributor) {
            // Skip hidden contributors
            if ($contributor['hidden'] ?? false) {
                continue;
            }

            // Check if this is an author role
            $roleUri = $contributor['role']['uri'] ?? '';
            if (!str_contains($roleUri, '/author')) {
                continue;
            }

            // Extract name from nested structure
            $name = $contributor['name'] ?? [];
            if (empty($name)) {
                continue;
            }

            $author = [];

            if (isset($name['lastName'])) {
                $author['family'] = $name['lastName'];
            }

            if (isset($name['firstName'])) {
                $author['given'] = $name['firstName'];
            }

            // Only add if we have at least a family name
            if (!empty($author['family'])) {
                $authors[] = $author;
            }
        }

        return $authors;
    }

    /**
     * Transform container information (journal, book, etc.)
     *
     * CRITICAL FIX: Journal name is in journalAssociation.title.title
     *
     * @param array $data Research output data
     * @param string $locale Locale for multilingual content
     * @return array CSL container fields
     */
    private function transformContainer(array $data, string $locale): array
    {
        $csl = [];

        // Journal (for articles)
        if (isset($data['journalAssociation'])) {
            $journal = $data['journalAssociation'];

            // Journal title - handle potential multilingual structure
            $journalTitle = null;
            if (isset($journal['title']['title'])) {
                $journalTitle = $this->extractTextValue($journal['title']['title'], $locale);
            } elseif (isset($journal['title']) && is_string($journal['title'])) {
                $journalTitle = $journal['title'];
            } elseif (isset($journal['title'])) {
                $journalTitle = $this->extractTextValue($journal['title'], $locale);
            }

            if (!empty($journalTitle) && is_string($journalTitle)) {
                $csl['container-title'] = $journalTitle;
            }

            // ISSN - ensure string
            if (isset($journal['issn']['issn'])) {
                $issn = $journal['issn']['issn'];
                if (is_string($issn)) {
                    $csl['ISSN'] = $issn;
                } elseif (is_array($issn)) {
                    $csl['ISSN'] = $this->extractTextValue($issn, $locale);
                }
            } elseif (isset($journal['issn']) && is_string($journal['issn'])) {
                $csl['ISSN'] = $journal['issn'];
            }
        }

        // Book container (for chapters)
        if (isset($data['bookSeries']['title'])) {
            $bookTitle = $this->extractTextValue($data['bookSeries']['title'], $locale);
            if (!empty($bookTitle) && is_string($bookTitle)) {
                $csl['collection-title'] = $bookTitle;
            }
        }

        // Host publication (for book chapters, conference papers)
        if (isset($data['hostPublicationTitle'])) {
            $hostTitle = $this->extractTextValue($data['hostPublicationTitle'], $locale);
            if (!empty($hostTitle) && is_string($hostTitle)) {
                $csl['container-title'] = $hostTitle;
            }
        }

        return $csl;
    }

    /**
     * Transform identifiers (DOI, ISBN, etc.)
     *
     * CRITICAL FIX: DOI is in electronicVersions array, NOT in identifiers!
     *
     * @param array $data Research output data
     * @return array CSL identifier fields
     */
    private function transformIdentifiers(array $data): array
    {
        $csl = [];

        // DOI - CRITICAL: Search in electronicVersions array
        if (isset($data['electronicVersions']) && is_array($data['electronicVersions'])) {
            foreach ($data['electronicVersions'] as $version) {
                if ($version['typeDiscriminator'] === 'DoiElectronicVersion' && isset($version['doi'])) {
                    $csl['DOI'] = is_string($version['doi']) ? $version['doi'] : (string)$version['doi'];
                    break;
                }
            }
        }

        // Other identifiers (Scopus ID, PubMed ID, etc.)
        if (isset($data['identifiers']) && is_array($data['identifiers'])) {
            foreach ($data['identifiers'] as $identifier) {
                $source = $identifier['idSource'] ?? '';
                $value = $identifier['value'] ?? '';

                if (empty($value) || !is_string($value)) {
                    continue;
                }

                switch (strtolower($source)) {
                    case 'scopus':
                        $csl['scopus-id'] = $value;
                        break;
                    case 'pubmed':
                        $csl['PMID'] = $value;
                        break;
                    case 'isbn':
                        $csl['ISBN'] = $value;
                        break;
                }
            }
        }

        // ISBN from book information - ensure string
        if (isset($data['isbn']['isbn'])) {
            $csl['ISBN'] = is_string($data['isbn']['isbn'])
                ? $data['isbn']['isbn']
                : (string)($data['isbn']['isbn'] ?? '');
        }

        return $csl;
    }

    /**
     * Extract keywords from keywordGroups
     *
     * @param array $data Research output data
     * @param string $locale Preferred locale
     * @return array Array of keyword strings
     */
    private function extractKeywords(array $data, string $locale): array
    {
        $allKeywords = [];

        if (!isset($data['keywordGroups']) || !is_array($data['keywordGroups'])) {
            return $allKeywords;
        }

        foreach ($data['keywordGroups'] as $group) {
            $discriminator = $group['typeDiscriminator'] ?? '';

            if ($discriminator === 'FreeKeywordsKeywordGroup') {
                // Free keywords
                if (isset($group['keywords']) && is_array($group['keywords'])) {
                    foreach ($group['keywords'] as $keywordObj) {
                        if (isset($keywordObj['freeKeywords']) && is_array($keywordObj['freeKeywords'])) {
                            $allKeywords = array_merge($allKeywords, $keywordObj['freeKeywords']);
                        }
                    }
                }
            } elseif ($discriminator === 'ClassificationsKeywordGroup') {
                // Classifications (subject areas)
                if (isset($group['classifications']) && is_array($group['classifications'])) {
                    foreach ($group['classifications'] as $classification) {
                        if (isset($classification['term'][$locale])) {
                            $allKeywords[] = $classification['term'][$locale];
                        } elseif (isset($classification['term']['en_GB'])) {
                            $allKeywords[] = $classification['term']['en_GB'];
                        }
                    }
                }
            }
        }

        return array_unique(array_filter($allKeywords));
    }

    /**
     * Map Pure publication type to CSL type
     *
     * @param array $data Research output data
     * @return string CSL type
     */
    private function mapPublicationType(array $data): string
    {
        // Get type URI
        $typeUri = $data['type']['uri'] ?? '';

        if (empty($typeUri)) {
            return 'article';
        }

        // Extract the relevant part from URI
        // e.g., /dk/atira/pure/researchoutput/researchoutputtypes/contributiontojournal/article
        // -> contributiontojournal/article

        foreach (self::TYPE_MAPPING as $pattern => $cslType) {
            if (str_contains($typeUri, $pattern)) {
                return $cslType;
            }
        }

        // Fallback based on typeDiscriminator
        $discriminator = strtolower($data['typeDiscriminator'] ?? '');
        if (str_contains($discriminator, 'journal')) {
            return 'article-journal';
        } elseif (str_contains($discriminator, 'conference')) {
            return 'paper-conference';
        } elseif (str_contains($discriminator, 'book')) {
            return 'book';
        }

        return 'article';
    }

    /**
     * Extract language code from Pure language URI
     *
     * @param string $uri Language URI like "/dk/atira/pure/core/languages/en_GB"
     * @return string ISO language code
     */
    private function extractLanguageCode(string $uri): string
    {
        // Extract last part of URI
        if (preg_match('/\/([a-z]{2}_[A-Z]{2})$/', $uri, $matches)) {
            // Convert en_GB to en-GB for CSL
            return str_replace('_', '-', $matches[1]);
        }

        return 'en';
    }

    /**
     * Extract text value from Pure data structure
     * Handles multilingual content
     *
     * @param mixed $value Pure field value
     * @param string $locale Preferred locale
     * @return string Extracted text
     */
    private function extractTextValue($value, string $locale = 'en_GB'): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            return '';
        }

        // Direct value
        if (isset($value['value']) && is_string($value['value'])) {
            return $value['value'];
        }

        // Locale map in value
        if (isset($value['value']) && is_array($value['value'])) {
            return $this->extractFromLocaleMap($value['value'], $locale);
        }

        // Direct locale map
        if (isset($value[$locale])) {
            return $value[$locale];
        }

        // Try English fallback
        if (isset($value['en_GB'])) {
            return $value['en_GB'];
        }

        // Return first available value
        foreach ($value as $v) {
            if (is_string($v)) {
                return $v;
            }
        }

        return '';
    }

    /**
     * Extract value from locale map
     *
     * @param array $localeMap Map of locale to text
     * @param string $locale Preferred locale
     * @return string Extracted text
     */
    private function extractFromLocaleMap(array $localeMap, string $locale): string
    {
        // Exact match
        if (isset($localeMap[$locale])) {
            return $localeMap[$locale];
        }

        // Language family match (de matches de_DE)
        $lang = substr($locale, 0, 2);
        foreach ($localeMap as $key => $value) {
            if (str_starts_with($key, $lang)) {
                return $value;
            }
        }

        // English fallback
        foreach ($localeMap as $key => $value) {
            if (str_starts_with($key, 'en')) {
                return $value;
            }
        }

        // First available
        return reset($localeMap) ?: '';
    }

    /**
     * Transform batch of research outputs
     *
     * @param array $items Array of research outputs
     * @param string|null $locale Optional locale
     * @return array Array of CSL-JSON items
     */
    public function transformBatch(array $items, ?string $locale = null): array
    {
        return array_map(
            fn($item) => $this->transformResearchOutput($item, $locale),
            $items
        );
    }
}
