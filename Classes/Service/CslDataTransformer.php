<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

/**
 * Transforms Pure API data to CSL-JSON format
 *
 * Converts research output data from Pure (XML or OpenAPI) into CSL-JSON format
 * compatible with Citation Style Language 1.0.2 specification.
 *
 * @see https://citeproc-js.readthedocs.io/en/latest/csl-json/markup.html
 */
class CslDataTransformer
{
    /**
     * Pure publication type to CSL type mapping
     */
    private const TYPE_MAPPING = [
        'contributionToJournal' => 'article-journal',
        'contributionToBookAnthology' => 'chapter',
        'book' => 'book',
        'contributionToConference' => 'paper-conference',
        'thesis' => 'thesis',
        'patent' => 'patent',
        'report' => 'report',
        'workingPaper' => 'manuscript',
        'other' => 'article',
    ];

    /**
     * Transform research output to CSL-JSON
     *
     * @param array $data Pure research output data
     * @return array CSL-JSON formatted data
     */
    public function transformResearchOutput(array $data): array
    {
        $csl = [];

        // Required fields
        $csl['id'] = $data['uuid'] ?? uniqid('pure_');
        $csl['type'] = $this->mapPublicationType($data);

        // Title
        if (isset($data['title'])) {
            $csl['title'] = $data['title'];
        }

        // Authors/Contributors
        if (isset($data['contributors']) && is_array($data['contributors'])) {
            $csl['author'] = $this->transformContributors($data['contributors']);
        }

        // Publication date
        $csl = array_merge($csl, $this->transformDate($data));

        // Container (journal, book, etc.)
        $csl = array_merge($csl, $this->transformContainer($data));

        // Identifiers (DOI, ISBN, ISSN)
        $csl = array_merge($csl, $this->transformIdentifiers($data));

        // Volume, Issue, Pages
        if (isset($data['journal']['volume']) || isset($data['volume'])) {
            $csl['volume'] = $data['journal']['volume'] ?? $data['volume'];
        }

        if (isset($data['journal']['issue']) || isset($data['issue'])) {
            $csl['issue'] = $data['journal']['issue'] ?? $data['issue'];
        }

        if (isset($data['journal']['pages']) || isset($data['pages'])) {
            $csl['page'] = $data['journal']['pages'] ?? $data['pages'];
        }

        // Abstract
        if (isset($data['abstract'])) {
            $csl['abstract'] = $data['abstract'];
        }

        // Keywords
        if (isset($data['keywords']) && is_array($data['keywords'])) {
            $csl['keyword'] = implode(', ', $data['keywords']);
        }

        // Publisher
        if (isset($data['publisher'])) {
            $csl['publisher'] = is_array($data['publisher'])
                ? ($data['publisher']['name'] ?? '')
                : $data['publisher'];
        }

        // Publisher place
        if (isset($data['publisherLocation']) || isset($data['publisher']['location'])) {
            $csl['publisher-place'] = $data['publisherLocation'] ?? $data['publisher']['location'];
        }

        // Edition
        if (isset($data['edition'])) {
            $csl['edition'] = $data['edition'];
        }

        // Language
        if (isset($data['language'])) {
            $csl['language'] = $this->mapLanguage($data['language']);
        }

        // URL
        if (isset($data['portalUrl']) || isset($data['url'])) {
            $csl['URL'] = $data['portalUrl'] ?? $data['url'];
        }

        // Accessed date (for URLs)
        if (isset($csl['URL'])) {
            $csl['accessed'] = $this->formatCslDate(date('Y-m-d'));
        }

        return $csl;
    }

    /**
     * Map Pure publication type to CSL type
     *
     * @param array $data Research output data
     * @return string CSL type
     */
    private function mapPublicationType(array $data): string
    {
        $pureType = $data['type'] ?? $data['typeDescription'] ?? 'other';

        // Check if it's a known type
        if (isset(self::TYPE_MAPPING[$pureType])) {
            return self::TYPE_MAPPING[$pureType];
        }

        // Try to infer from data
        if (isset($data['journal'])) {
            return 'article-journal';
        }

        if (isset($data['conference'])) {
            return 'paper-conference';
        }

        if (isset($data['bookTitle']) || isset($data['book'])) {
            return 'chapter';
        }

        // Default
        return 'article';
    }

    /**
     * Transform contributors to CSL author format
     *
     * @param array $contributors Pure contributors
     * @return array CSL authors
     */
    private function transformContributors(array $contributors): array
    {
        $authors = [];

        foreach ($contributors as $contributor) {
            // Skip if not an author/contributor
            $role = $contributor['role'] ?? '';
            if ($role && !in_array($role, ['author', 'creator', ''], true)) {
                continue;
            }

            $author = [];

            // Handle different name formats
            if (isset($contributor['name'])) {
                $name = $contributor['name'];

                if (isset($name['lastName'])) {
                    $author['family'] = $name['lastName'];
                }

                if (isset($name['firstName'])) {
                    $author['given'] = $name['firstName'];
                }
            } elseif (isset($contributor['person'])) {
                // Handle person reference
                $person = $contributor['person'];
                if (isset($person['name'])) {
                    $author['family'] = $person['name']['lastName'] ?? '';
                    $author['given'] = $person['name']['firstName'] ?? '';
                }
            }

            // Parse literal name if structured name not available
            if (empty($author) && isset($contributor['fullName'])) {
                $parsed = $this->parseName($contributor['fullName']);
                $author = $parsed;
            }

            if (!empty($author)) {
                $authors[] = $author;
            }
        }

        return $authors;
    }

    /**
     * Parse name string into family and given names
     *
     * @param string $fullName Full name string
     * @return array CSL name parts
     */
    private function parseName(string $fullName): array
    {
        // Simple parsing: assume "Last, First" or "First Last"
        if (str_contains($fullName, ',')) {
            [$family, $given] = explode(',', $fullName, 2);
            return [
                'family' => trim($family),
                'given' => trim($given),
            ];
        }

        // Assume last word is family name
        $parts = explode(' ', trim($fullName));
        $family = array_pop($parts);
        $given = implode(' ', $parts);

        return [
            'family' => $family,
            'given' => $given ?: '',
        ];
    }

    /**
     * Transform publication date to CSL format
     *
     * @param array $data Research output data
     * @return array CSL date fields
     */
    private function transformDate(array $data): array
    {
        $cslDate = [];

        // Try different date fields
        $year = $data['publicationYear'] ?? $data['year'] ?? null;
        $date = $data['publicationDate'] ?? $data['date'] ?? null;

        if ($date) {
            $cslDate['issued'] = $this->formatCslDate($date);
        } elseif ($year) {
            $cslDate['issued'] = [
                'date-parts' => [[(int)$year]],
            ];
        }

        return $cslDate;
    }

    /**
     * Format date for CSL
     *
     * @param string $date Date string
     * @return array CSL date format
     */
    private function formatCslDate(string $date): array
    {
        try {
            $dt = new \DateTime($date);

            return [
                'date-parts' => [[
                    (int)$dt->format('Y'),
                    (int)$dt->format('m'),
                    (int)$dt->format('d'),
                ]],
            ];
        } catch (\Exception $e) {
            // Fallback to just year if parsing fails
            $year = (int)substr($date, 0, 4);
            return [
                'date-parts' => [[$year]],
            ];
        }
    }

    /**
     * Transform container (journal, book, etc.) information
     *
     * @param array $data Research output data
     * @return array CSL container fields
     */
    private function transformContainer(array $data): array
    {
        $container = [];

        // Journal
        if (isset($data['journal']['title'])) {
            $container['container-title'] = $data['journal']['title'];
        } elseif (isset($data['journalTitle'])) {
            $container['container-title'] = $data['journalTitle'];
        }

        // Book title (for chapters)
        if (isset($data['bookTitle']) || isset($data['book']['title'])) {
            $container['container-title'] = $data['bookTitle'] ?? $data['book']['title'];
        }

        // Conference
        if (isset($data['conference']['name'])) {
            $container['event'] = $data['conference']['name'];
        } elseif (isset($data['event'])) {
            $container['event'] = $data['event'];
        }

        // Series
        if (isset($data['series'])) {
            $container['collection-title'] = $data['series'];
        }

        return $container;
    }

    /**
     * Transform identifiers (DOI, ISBN, ISSN)
     *
     * @param array $data Research output data
     * @return array CSL identifier fields
     */
    private function transformIdentifiers(array $data): array
    {
        $identifiers = [];

        // DOI
        if (isset($data['doi'])) {
            $identifiers['DOI'] = $data['doi'];
        }

        // ISBN
        if (isset($data['isbn'])) {
            $identifiers['ISBN'] = is_array($data['isbn'])
                ? implode(', ', $data['isbn'])
                : $data['isbn'];
        }

        // ISSN
        if (isset($data['journal']['issn'])) {
            $identifiers['ISSN'] = $data['journal']['issn'];
        } elseif (isset($data['issn'])) {
            $identifiers['ISSN'] = $data['issn'];
        }

        // PMID (PubMed ID)
        if (isset($data['pmid'])) {
            $identifiers['PMID'] = $data['pmid'];
        }

        return $identifiers;
    }

    /**
     * Map Pure language code to CSL language
     *
     * @param string $language Pure language code
     * @return string CSL language code
     */
    private function mapLanguage(string $language): string
    {
        $mapping = [
            'de_DE' => 'de-DE',
            'en_US' => 'en-US',
            'en_GB' => 'en-GB',
            'de' => 'de-DE',
            'en' => 'en-US',
            'fr' => 'fr-FR',
            'es' => 'es-ES',
            'it' => 'it-IT',
        ];

        return $mapping[$language] ?? $language;
    }
}
