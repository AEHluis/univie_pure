<?php

declare(strict_types=1);

namespace Univie\UniviePure\Endpoint;

use Univie\UniviePure\Service\OpenApi\OpenApiException;

/**
 * Research output endpoint for Pure API
 *
 * Handles all research-output-related API calls including:
 * - List and single item fetching
 * - CSL citation rendering (batch and individual)
 * - BibTeX export
 * - Data transformation for template compatibility
 */
class ResearchOutputEndpoint extends AbstractEndpoint
{
    protected function getEndpointPath(): string
    {
        return '/research-outputs';
    }

    protected function renderItem(array $item, string $view): string
    {
        return $this->renderingService->renderResearchOutput($item, $view);
    }

    /**
     * {@inheritdoc}
     *
     * Override to handle CSL rendering optimization
     */
    public function getAll(array $params = []): array
    {
        try {
            $queryParams = $this->buildQueryParams($params);
            $view = $params['view'] ?? $params['rendering'] ?? $this->getDefaultListView();
            $view = $this->mapView($view);

            // CSL styles are rendered locally from structured OpenAPI data.
            if ($this->renderingService->isCslStyle($view)) {
                unset($queryParams['view']);
            }

            $response = $this->client->get($this->getEndpointPath(), $queryParams);
            $collection = $this->parser->parseCollection($response);

            $locale = $params['locale'] ?? 'en_GB';

            // Optimize CSL rendering: use batch rendering for citation styles
            if ($this->renderingService->isCslStyle($view)) {
                $this->addBatchCslRendering($collection['items'], $view, $locale);
            } else {
                $this->addIndividualRendering($collection['items'], $view, $locale);
            }

            return $this->normalizeCollectionResponse($collection);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get research outputs', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Override to apply data transformation for templates
     */
    public function getOne(string $uuid, array $params = []): ?array
    {
        $queryParams = $this->buildSingleItemParams($params);
        $view = $params['view'] ?? $params['rendering'] ?? $this->getDefaultDetailView();
        $view = $this->mapView($view);

        // CSL styles are local renderers, not OpenAPI views.
        if ($this->renderingService->isCslStyle($view)) {
            unset($queryParams['view']);
        }

        try {
            $response = $this->client->get("{$this->getEndpointPath()}/{$uuid}", $queryParams);

            // Transform OpenAPI response to template-compatible format
            $locale = $params['locale'] ?? 'en_GB';
            $response = $this->transformForTemplate($response, $locale);

            // Add HTML rendering for template compatibility
            $response['rendering'] = $this->renderingService->renderResearchOutput($response, $view, null, $locale);

            return $response;
        } catch (OpenApiException $e) {
            if ($e->isNotFoundError()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Get BibTeX export for a research output
     *
     * @param string $uuid Research output UUID
     * @param array $params Query parameters
     * @return string|null BibTeX string or null if not found
     */
    public function getBibtex(string $uuid, array $params = []): ?string
    {
        try {
            $response = $this->client->get("{$this->getEndpointPath()}/{$uuid}", [
                'format' => 'bibtex',
            ], [
                'Accept' => 'text/plain',
            ]);

            return $response['content'] ?? $response['bibtex'] ?? null;
        } catch (OpenApiException $e) {
            if ($e->isNotFoundError()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Transform OpenAPI research output response to template-compatible format
     *
     * OpenAPI returns flat localized structures (term.en_GB, term.de_DE)
     * but templates expect array-based structures (term.text.0.value with locale).
     *
     * @param array $response Raw OpenAPI response
     * @param string $locale Current locale (e.g., 'de_DE', 'en_GB')
     * @return array Transformed response compatible with templates
     */
    public function transformForTemplate(array $response, string $locale): array
    {
        // Transform type term structure
        if (isset($response['type']['term'])) {
            $response['type']['term'] = $this->transformLocalizedTerm($response['type']['term']);
        }

        // Transform publication status terms
        if (isset($response['publicationStatuses']) && is_array($response['publicationStatuses'])) {
            foreach ($response['publicationStatuses'] as &$status) {
                if (isset($status['publicationStatus']['term'])) {
                    $status['publicationStatus']['term'] = $this->transformLocalizedTerm($status['publicationStatus']['term']);
                }
            }
            unset($status);
        }

        // Transform abstract to text array format
        if (isset($response['abstract']) && is_array($response['abstract'])) {
            $response['abstract'] = $this->transformLocalizedContent($response['abstract']);
        }

        // Transform organisations (organizations in OpenAPI) with names
        if (isset($response['organizations']) && is_array($response['organizations'])) {
            $response['organisationalUnits'] = $this->enrichOrganizations($response['organizations'], $locale);
        }

        // Transform external organizations with names
        if (isset($response['externalOrganizations']) && is_array($response['externalOrganizations'])) {
            $response['externalOrganisations'] = $this->enrichExternalOrganizations($response['externalOrganizations'], $locale);
        }

        // Transform journal association
        if (isset($response['journalAssociation'])) {
            $journal = &$response['journalAssociation'];
            if (isset($journal['title']['title'])) {
                $journal['title']['value'] = $journal['title']['title'];
            }
            if (isset($journal['issn']['issn'])) {
                $journal['issn']['value'] = $journal['issn']['issn'];
            }
        }

        // Transform keyword groups
        if (isset($response['keywordGroups']) && is_array($response['keywordGroups'])) {
            $response['keywordGroups'] = $this->transformKeywordGroups($response['keywordGroups']);
        }

        // Transform electronic versions access type terms
        if (isset($response['electronicVersions']) && is_array($response['electronicVersions'])) {
            foreach ($response['electronicVersions'] as &$ev) {
                if (isset($ev['accessType']['term'])) {
                    $ev['accessType']['term'] = $this->transformLocalizedTerm($ev['accessType']['term']);
                }
                if (isset($ev['versionType']['term'])) {
                    $ev['versionType']['term'] = $this->transformLocalizedTerm($ev['versionType']['term']);
                }
            }
            unset($ev);
        }

        // Add info.portalUrl wrapper for backward compatibility
        if (isset($response['portalUrl']) && !isset($response['info']['portalUrl'])) {
            $response['info']['portalUrl'] = $response['portalUrl'];
        }

        return $response;
    }

    /**
     * Transform localized term from OpenAPI format to template format
     *
     * OpenAPI: { "en_GB": "Article", "de_DE": "Artikel" }
     * Template: { "text": [{ "value": "Article", "locale": "en_GB" }, ...] }
     */
    private function transformLocalizedTerm(array $term): array
    {
        $result = $term;

        $texts = [];
        foreach ($term as $locale => $value) {
            if (is_string($value) && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
                $texts[] = [
                    'value' => $value,
                    'locale' => $locale,
                ];
            }
        }
        if (!empty($texts)) {
            $result['text'] = $texts;
        }

        return $result;
    }

    /**
     * Transform localized content (like abstract) to text array format
     */
    private function transformLocalizedContent(array $content): array
    {
        $result = $content;
        $texts = [];

        foreach ($content as $locale => $value) {
            if (is_string($value) && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
                $texts[] = [
                    'value' => $value,
                    'locale' => $locale,
                ];
            }
        }

        if (!empty($texts)) {
            $result['text'] = $texts;
        }

        return $result;
    }

    /**
     * Transform keyword groups to template-compatible format
     */
    private function transformKeywordGroups(array $keywordGroups): array
    {
        $result = [];

        foreach ($keywordGroups as $group) {
            $transformedGroup = $group;

            if (isset($group['type']['term'])) {
                $transformedGroup['type']['term'] = $this->transformLocalizedTerm($group['type']['term']);
            }

            if (isset($group['name']) && is_array($group['name'])) {
                $transformedGroup['name'] = $this->transformLocalizedContent($group['name']);
            }

            // Handle FreeKeywordsKeywordGroup
            if (isset($group['keywords']) && is_array($group['keywords'])) {
                $keywordContainers = [];
                foreach ($group['keywords'] as $keyword) {
                    if (isset($keyword['freeKeywords']) && is_array($keyword['freeKeywords'])) {
                        foreach ($keyword['freeKeywords'] as $freeKeyword) {
                            $keywordContainers[] = [
                                'structuredKeyword' => [
                                    'term' => [
                                        'text' => [
                                            [
                                                'value' => $freeKeyword,
                                                'locale' => $keyword['locale'] ?? 'en_GB',
                                            ],
                                        ],
                                    ],
                                ],
                            ];
                        }
                    }
                }
                $transformedGroup['keywordContainers'] = $keywordContainers;
            }

            // Handle ClassificationsKeywordGroup
            if (isset($group['classifications']) && is_array($group['classifications'])) {
                $keywordContainers = [];
                foreach ($group['classifications'] as $classification) {
                    if (isset($classification['term'])) {
                        $keywordContainers[] = [
                            'structuredKeyword' => [
                                'term' => $this->transformLocalizedTerm($classification['term']),
                            ],
                        ];
                    }
                }
                $transformedGroup['keywordContainers'] = $keywordContainers;
            }

            $result[] = $transformedGroup;
        }

        return $result;
    }

    /**
     * Enrich organizations with names from the API
     */
    private function enrichOrganizations(array $organizations, string $locale): array
    {
        $result = [];

        foreach ($organizations as $org) {
            $uuid = $org['uuid'] ?? '';
            if (empty($uuid)) {
                continue;
            }

            try {
                $orgDetails = $this->client->get("/organizational-units/{$uuid}", ['locale' => $locale]);
                $name = $this->extractOrganizationName($orgDetails, $locale);
            } catch (\Throwable $e) {
                $name = null;
            }

            $result[] = [
                'uuid' => $uuid,
                'name' => $name ?? [
                    'text' => [
                        ['value' => 'Unknown Organization', 'locale' => $locale],
                    ],
                ],
            ];
        }

        return $result;
    }

    /**
     * Enrich external organizations with names
     */
    private function enrichExternalOrganizations(array $organizations, string $locale): array
    {
        $result = [];

        foreach ($organizations as $org) {
            $uuid = $org['uuid'] ?? '';
            if (empty($uuid)) {
                continue;
            }

            try {
                $orgDetails = $this->client->get("/external-organizations/{$uuid}", ['locale' => $locale]);
                $name = $this->extractOrganizationName($orgDetails, $locale);
            } catch (\Throwable $e) {
                $name = null;
            }

            $result[] = [
                'uuid' => $uuid,
                'name' => $name ?? [
                    'text' => [
                        ['value' => 'External Organization', 'locale' => $locale],
                    ],
                ],
            ];
        }

        return $result;
    }

    /**
     * Extract organization name from API response and transform to template format
     */
    private function extractOrganizationName(array $orgDetails, string $locale): ?array
    {
        if (!isset($orgDetails['name'])) {
            return null;
        }

        $name = $orgDetails['name'];

        if (isset($name['text']) && is_array($name['text'])) {
            return $name;
        }

        if (is_array($name)) {
            return $this->transformLocalizedContent($name);
        }

        if (is_string($name)) {
            return [
                'text' => [
                    ['value' => $name, 'locale' => $locale],
                ],
            ];
        }

        return null;
    }

    /**
     * Add batch CSL rendering to items (optimized for list views)
     */
    private function addBatchCslRendering(array &$items, string $style, string $locale = 'en_GB'): void
    {
        if (empty($items)) {
            return;
        }

        $bibliography = $this->renderingService->renderBibliography($items, $style, $locale);
        $entries = $this->parseBibliographyEntries($bibliography);

        if (empty($entries) && !empty($bibliography)) {
            $this->logger->warning('CSL bibliography parsing returned no entries', [
                'style' => $style,
                'locale' => $locale,
                'itemCount' => count($items),
                'bibliographyLength' => strlen($bibliography),
                'bibliographyPreview' => substr($bibliography, 0, 500),
            ]);
        }

        foreach ($items as $index => &$item) {
            $item['rendering'] = $entries[$index] ?? $this->renderFallbackEntry($item);
        }
    }

    /**
     * Add individual rendering to items (for Fluid templates)
     */
    private function addIndividualRendering(array &$items, string $view, string $locale = 'en_GB'): void
    {
        foreach ($items as &$item) {
            $item['rendering'] = $this->renderingService->renderResearchOutput($item, $view, null, $locale);
        }
    }

    /**
     * Parse citeproc-php bibliography HTML into individual entries
     */
    private function parseBibliographyEntries(string $bibliography): array
    {
        $entries = [];
        $offset = 0;
        $bibLength = strlen($bibliography);

        while (($startPos = strpos($bibliography, '<div class="csl-entry"', $offset)) !== false) {
            $tagEnd = strpos($bibliography, '>', $startPos);
            if ($tagEnd === false) {
                break;
            }

            $pos = $tagEnd + 1;
            $depth = 1;

            while ($depth > 0 && $pos < $bibLength) {
                $nextOpen = strpos($bibliography, '<div', $pos);
                $nextClose = strpos($bibliography, '</div>', $pos);

                if ($nextClose === false) {
                    break;
                }

                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    $depth++;
                    $pos = $nextOpen + 4;
                } else {
                    $depth--;
                    if ($depth === 0) {
                        $entries[] = substr($bibliography, $startPos, $nextClose + 6 - $startPos);
                    }
                    $pos = $nextClose + 6;
                }
            }

            $offset = $pos;
        }

        return $entries;
    }

    /**
     * Render fallback entry when parsing fails
     */
    private function renderFallbackEntry(array $item): string
    {
        $title = 'Untitled';
        if (isset($item['title'])) {
            if (is_array($item['title']) && isset($item['title']['value'])) {
                $title = $item['title']['value'];
            } elseif (is_string($item['title'])) {
                $title = $item['title'];
            }
        }

        $year = $item['publicationYear'] ?? '';

        return sprintf(
            '<div class="csl-entry">%s%s</div>',
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            $year ? ' (' . htmlspecialchars((string)$year, ENT_QUOTES, 'UTF-8') . ')' : ''
        );
    }
}
