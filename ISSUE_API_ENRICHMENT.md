# Feature: Publication Detail Enhancement (TYPO3 v12, Pure API 524)

## 1. Goal

Enhance publication detail pages with richer metadata using a **Pure-first strategy**:
1. Use available fields from Pure response directly.
2. Add external DOI enrichment only as optional fallback/extension.

Pure API reference:  
`https://research-portal.uu.nl/ws/api/524/api-docs/index.html`

## 2. Why This Scope Is Realistic

The real `contributionToJournal` payload already contains key fields needed for details-page improvements:
1. `openAccessPermission`
2. `electronicVersions.electronicVersion.doi`
3. `totalScopusCitations`
4. `fieldWeightedCitationImpact`
5. `scopusMetrics.scopusMetric[*]`
6. `publicationStatuses.publicationStatus[current=true]`
7. `additionalLinks.additionalLink` (e.g. Scopus link)
8. `info.modifiedDate`, `info.portalUrl`
9. `visibility.key`

## 3. Scope

In scope (MVP):
1. Detail page only (`showAction`, `what2show=publ`).
2. New consolidated metadata block based on Pure data.
3. Optional external DOI enrichment (OpenAlex + Unpaywall) behind feature flag.
4. Caching, timeout, graceful degradation, logging.

Out of scope (Phase 2+):
1. ORCID matching.
2. Crossref/DataCite/ROR/OpenAIRE/CORE/arXiv.
3. `data.uni-hannover.de` CKAN integration.
4. List-page enrichment.

## 4. Functional Requirements

### FR-1 Pure-First Metadata Mapping

From `contributionToJournal`, map and render:
1. Publication status/date from `publicationStatuses` with `current="true"`.
2. OA status from `openAccessPermission.term.text`.
3. DOI list from `electronicVersions`.
4. Citation values:
   1. `totalScopusCitations`
   2. `fieldWeightedCitationImpact`
   3. latest value from `scopusMetrics.scopusMetric` (highest year)
5. External evidence links from `additionalLinks` (e.g. Scopus publication URL).
6. Last update timestamp from `info.modifiedDate`.
7. Scopus source note: all Scopus values come from Pure response fields only (no direct Scopus API call in MVP).

### FR-2 DOI Extraction and Normalization

1. Extract candidate DOIs from:
   1. `electronicVersions[*].doi`
   2. DOI-like values in `electronicVersions[*].link` (if present)
2. Normalize DOI:
   1. strip `https://doi.org/` prefix
   2. lower-case
3. Deduplicate DOIs.

### FR-3 Primary DOI Rule

1. Choose `primaryDoi`:
   1. Prefer non-preprint DOI (not `10.48550/arXiv...`)
   2. else first valid DOI
2. Keep all valid DOIs in output model.

### FR-4 Optional External Enrichment (Feature-Flagged)

Only when enabled and `primaryDoi` exists:
1. Unpaywall:
   1. `GET https://api.unpaywall.org/v2/{DOI}?email={configuredEmail}`
2. Optional OpenAlex (deferred toggle, not required for MVP acceptance):
   1. `GET https://api.openalex.org/works/https://doi.org/{DOI}`
3. Unpaywall mapping:
   1. `is_oa`
   2. `oa_status`
   3. `best_oa_location.url_for_pdf`
   4. `best_oa_location.url` and/or `best_oa_location.url_for_landing_page`
   5. `best_oa_location.version`
   6. `best_oa_location.license`
   7. `updated`
4. OpenAlex mapping (optional):
   1. `cited_by_count`
   2. `open_access.*`

### FR-5 Merge Rules

1. `statusPrimary` for OA:
   1. Pure `openAccessPermission` is default.
   2. Unpaywall may override only if configured (`enrichment.preferExternalOa = 1`).
2. Citations:
   1. Show Pure Scopus citations by default.
   2. No direct Scopus API call in MVP.
   3. Show OpenAlex citations as additional metric (not overwrite), if enabled.
3. If provider data missing/conflicting, keep Pure values.

### FR-6 Frontend Output

Render block `Publication Insights` with:
1. OA status (Pure; external badge if used).
2. DOI(s) with clean links.
3. Citation metrics:
   1. Scopus total
   2. FWCI
   3. optional OpenAlex cited_by_count (if OpenAlex enabled)
4. Best free full-text link (from Unpaywall if available).
5. Data source badges: `Pure`, optionally `OpenAlex`, `Unpaywall`.
6. Last updated date (`info.modifiedDate`).

## 5. Non-Functional Requirements

### NFR-1 Reliability

1. Details page must render even if external APIs fail.
2. No exception from enrichment may break existing publication rendering.

### NFR-2 Performance

1. External calls only if feature enabled and DOI available.
2. Timeouts:
   1. connect timeout: 2s
   2. total timeout: 4s

### NFR-3 Caching

1. Pure-derived mapped data may use existing extension cache lifecycle.
2. External cache keys:
   1. `enrichment:{provider}:{sha1(doi)}`
3. TTL:
   1. OpenAlex: 7 days
   2. Unpaywall: 7 days
4. Negative cache (`404/not found`): 24h.

### NFR-4 Compliance

1. Unpaywall email must be configured to enable Unpaywall.
2. Do not store personal data beyond publication metadata already public in Pure.

## 6. TYPO3 v12 Implementation

### 6.1 Services

1. `Classes/Service/Enrichment/PublicationInsightsService.php`
2. `Classes/Service/Enrichment/DoiExtractor.php`
3. `Classes/Service/Enrichment/UnpaywallClient.php` (MVP external provider, by flag)
4. `Classes/Service/Enrichment/OpenAlexClient.php` (optional by flag)

### 6.2 Controller Integration

1. In `Classes/Controller/PureController.php` `showAction`:
   1. keep existing `$view = $pub->getSinglePublication(...)`
   2. build insights from `$view`
   3. assign `publicationInsights` to Fluid

### 6.3 Template Integration

1. Add `Resources/Private/Partials/Pure/PublicationInsights.html`
2. Include in `Resources/Private/Templates/Pure/Show.html` after properties section.

### 6.4 Config

1. `enrichment.enabled = 1`
2. `enrichment.external.enabled = 0` (default off)
3. `enrichment.unpaywallEmail = ...`
4. `enrichment.preferExternalOa = 0`
5. `enrichment.timeout.connect = 2`
6. `enrichment.timeout.total = 4`
7. `enrichment.openalex.enabled = 0` (optional)

## 7. Output Contract (Fluid)

```php
[
  'dois' => ['10.1016/j.camwa.2024.12.011'],
  'primaryDoi' => '10.1016/j.camwa.2024.12.011',
  'pure' => [
    'oaStatus' => 'Closed',
    'citationsScopusTotal' => 0,
    'fwci' => 0.0,
    'scopusMetricLatest' => ['year' => 2025, 'value' => 0],
    'publishedDate' => '2025-02-01',
    'lastModified' => '2026-02-18T23:27:34+01:00',
  ],
  'external' => [
    'unpaywall' => [
      'enabled' => false,
      'ok' => false,
      'isOa' => null,
      'oaStatus' => null,
      'bestPdfUrl' => null,
      'bestUrl' => null,
      'version' => null,
      'license' => null,
      'updated' => null
    ],
    'openalex' => ['enabled' => false, 'ok' => false, 'citedByCount' => null],
  ],
  'sources' => ['Pure']
]
```

## 8. Acceptance Criteria

1. For a publication like UUID `15b80b43-5ca2-430f-8ce5-364a47e56459`:
   1. DOI is extracted as `10.1016/j.camwa.2024.12.011`.
   2. OA status is shown from `openAccessPermission`.
   3. Scopus citation values are shown from Pure.
2. If no DOI exists:
   1. Pure insights still render.
   2. External enrichment is skipped.
3. If external APIs fail:
   1. Page still renders with Pure insights.
   2. Failure is logged.
4. If external feature flag is off:
   1. no external requests are executed.
5. Scopus values are always read from Pure fields (`totalScopusCitations`, `fieldWeightedCitationImpact`, `scopusMetrics`), not from Scopus API.

## 9. Test Plan

1. Unit:
   1. DOI normalization.
   2. latest `scopusMetrics` selection.
   3. current publication status extraction.
2. Integration:
   1. mapping from real-like Pure XML/array response.
   2. external provider mapping with mocked PSR-18 responses.
3. Functional:
   1. detail page renders with and without DOI.
   2. feature flag toggles external requests.

## 10. Rollout

1. Deploy with `enrichment.external.enabled = 0`.
2. Validate Pure-only insights on staging.
3. Enable external enrichment on staging for selected records.
4. Enable in production after monitoring.

## 11. Deferred Appendix: data.uni-hannover.de (CKAN)

Not part of MVP. Re-evaluate later if needed.

Notes:
1. CKAN datasets are typically mapped to Pure `datasets`, not to `research-output`.
2. Since dataset URLs are already persisted in Pure, added value for MVP is limited.
3. If implemented later, only use strong identifiers (`doi`, explicit related publication id/doi).
