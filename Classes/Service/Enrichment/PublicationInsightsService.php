<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\Enrichment;

class PublicationInsightsService
{
    public function __construct(
        private readonly DoiExtractor $doiExtractor,
        private readonly UnpaywallClient $unpaywallClient
    ) {
    }

    public function build(array $publication, string $locale = 'de_DE'): array
    {
        $dois = $this->doiExtractor->extractFromPublication($publication);
        $primaryDoi = $this->doiExtractor->selectPrimaryDoi($dois);

        $pureOaStatus = $this->extractLocalizedTerm($publication['openAccessPermission']['term']['text'] ?? null, $locale);
        $publishedDate = $this->extractCurrentPublicationDate($publication['publicationStatuses'] ?? null);
        $scopusLatest = $this->extractLatestScopusMetric($publication['scopusMetrics']['scopusMetric'] ?? null);
        $pureDocuments = $this->extractPureDocumentCandidates($publication);

        $insights = [
            'enabled' => true,
            'dois' => $dois,
            'primaryDoi' => $primaryDoi,
            'pure' => [
                'oaStatus' => $pureOaStatus,
                'citationsScopusTotal' => $this->toNullableInt($publication['totalScopusCitations'] ?? null),
                'fwci' => $this->toNullableFloat($publication['fieldWeightedCitationImpact'] ?? null),
                'scopusMetricLatest' => $scopusLatest,
                'publishedDate' => $publishedDate,
                'lastModified' => $publication['info']['modifiedDate'] ?? null,
                'documents' => $pureDocuments,
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
                    'updated' => null,
                ],
            ],
            'oa' => [
                'statusPrimary' => $pureOaStatus,
                'isOa' => null,
                'bestUrl' => null,
                'bestPdfUrl' => null,
                'license' => null,
            ],
            'document' => [
                'best' => $this->selectBestDocument($pureDocuments),
                'alternatives' => [],
            ],
            'display' => [
                'publishedDate' => $this->formatDate($publishedDate, $locale),
                'lastModified' => $this->formatDate($publication['info']['modifiedDate'] ?? null, $locale, true),
                'scopusCitations' => $this->formatNullableNumber($publication['totalScopusCitations'] ?? null, '0'),
                'fwci' => $this->formatNullableNumber($publication['fieldWeightedCitationImpact'] ?? null, '0.0'),
            ],
            'flags' => [
                'externalLookupUnavailable' => false,
            ],
        ];

        $externalEnabled = $this->isEnabled('ENRICHMENT_EXTERNAL_ENABLED', false);
        if (!$externalEnabled || $primaryDoi === null) {
            $insights['oa'] = $this->decorateOaState($insights['oa'], $insights['pure']['oaStatus'], false);
            return $insights;
        }

        $useUnpaywall = $this->isEnabled('ENRICHMENT_UNPAYWALL_ENABLED', true);
        if ($useUnpaywall) {
            $unpaywallEmail = trim((string)(getenv('UNPAYWALL_EMAIL') ?: ''));
            $unpaywall = $this->unpaywallClient->fetchByDoi($primaryDoi, $unpaywallEmail);
            $insights['external']['unpaywall'] = array_merge($insights['external']['unpaywall'], $unpaywall);
            if (!empty($unpaywall['ok'])) {
                $bestPdfUrl = trim((string)($unpaywall['bestPdfUrl'] ?? ''));
                $hasUsefulPdf = $bestPdfUrl !== '' && $this->isHttpUrl($bestPdfUrl);
                if ($hasUsefulPdf) {
                    $insights['oa']['isOa'] = $unpaywall['isOa'] ?? null;
                    $insights['oa']['bestPdfUrl'] = $bestPdfUrl;
                    $insights['oa']['license'] = $unpaywall['license'] ?? null;

                    $preferExternalOa = $this->isEnabled('ENRICHMENT_PREFER_EXTERNAL_OA', false);
                    if ($preferExternalOa && !empty($unpaywall['oaStatus'])) {
                        $insights['oa']['statusPrimary'] = $unpaywall['oaStatus'];
                    }
                }
            } else {
                $insights['flags']['externalLookupUnavailable'] = true;
            }
        }

        $insights['document'] = $this->buildDocumentSelection($insights, $pureDocuments);
        $insights['oa'] = $this->decorateOaState(
            $insights['oa'],
            $insights['pure']['oaStatus'],
            (bool)($insights['external']['unpaywall']['ok'] ?? false)
        );

        return $insights;
    }

    private function isEnabled(string $envName, bool $default): bool
    {
        $raw = getenv($envName);
        if ($raw === false || $raw === '') {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function extractCurrentPublicationDate(mixed $statuses): ?string
    {
        foreach ($this->toList($statuses) as $status) {
            if (!is_array($status)) {
                continue;
            }
            if (($status['current'] ?? null) !== 'true' && ($status['current'] ?? null) !== true) {
                continue;
            }
            $year = trim((string)($status['publicationDate']['year'] ?? ''));
            if ($year === '') {
                return null;
            }
            $month = str_pad(trim((string)($status['publicationDate']['month'] ?? '')), 2, '0', STR_PAD_LEFT);
            $day = str_pad(trim((string)($status['publicationDate']['day'] ?? '')), 2, '0', STR_PAD_LEFT);

            if ($month === '00') {
                return $year;
            }
            if ($day === '00') {
                return sprintf('%s-%s', $year, $month);
            }

            return sprintf('%s-%s-%s', $year, $month, $day);
        }

        return null;
    }

    /**
     * @return array{year:int,value:int}|null
     */
    private function extractLatestScopusMetric(mixed $metrics): ?array
    {
        $latest = null;
        foreach ($this->toList($metrics) as $metric) {
            if (!is_array($metric)) {
                continue;
            }
            $year = $this->toNullableInt($metric['year'] ?? null);
            $value = $this->toNullableInt($metric['value'] ?? null);
            if ($year === null || $value === null) {
                continue;
            }
            if ($latest === null || $year > $latest['year']) {
                $latest = ['year' => $year, 'value' => $value];
            }
        }
        return $latest;
    }

    private function extractLocalizedTerm(mixed $textNode, string $locale): ?string
    {
        $items = $this->toList($textNode);
        if ($items === []) {
            $value = is_string($textNode) ? trim($textNode) : '';
            return $value !== '' ? $value : null;
        }

        $fallback = null;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $value = trim((string)($item['value'] ?? ($item['text'] ?? '')));
            if ($value === '') {
                continue;
            }
            $itemLocale = trim((string)($item['locale'] ?? ''));
            if ($itemLocale === $locale) {
                return $value;
            }
            if ($fallback === null) {
                $fallback = $value;
            }
        }

        return $fallback;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric((string)$value)) {
            return null;
        }
        return (int)$value;
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric((string)$value)) {
            return null;
        }
        return (float)$value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractPureDocumentCandidates(array $publication): array
    {
        $documents = [];
        $versions = $publication['electronicVersions'] ?? [];
        foreach ($this->toList($versions) as $version) {
            if (!is_array($version)) {
                continue;
            }

            $link = trim((string)($version['link'] ?? ''));
            if ($link !== '' && $this->isHttpUrl($link)) {
                $documents[] = [
                    'url' => $link,
                    'source' => 'Pure',
                    'label' => $this->isPdfUrl($link) ? 'PDF' : 'Source',
                    'isPdf' => $this->isPdfUrl($link),
                    'score' => $this->isPdfUrl($link) ? 80 : 50,
                ];
            }

            $rawDoi = trim((string)($version['doi'] ?? ''));
            if ($rawDoi !== '') {
                $normalized = $this->doiExtractor->normalizeDoi($rawDoi);
                if ($normalized !== null) {
                    $documents[] = [
                        'url' => 'https://doi.org/' . $normalized,
                        'source' => 'Pure',
                        'label' => 'DOI',
                        'isPdf' => false,
                        'score' => 30,
                    ];
                }
            }
        }

        return $this->dedupeDocuments($documents);
    }

    /**
     * @param array<string,mixed> $insights
     * @param array<int,array<string,mixed>> $pureDocuments
     * @return array<string,mixed>
     */
    private function buildDocumentSelection(array $insights, array $pureDocuments): array
    {
        $documents = $pureDocuments;
        $unpaywall = $insights['external']['unpaywall'] ?? [];
        if (!empty($unpaywall['ok'])) {
            $pdf = trim((string)($unpaywall['bestPdfUrl'] ?? ''));
            if ($pdf !== '' && $this->isHttpUrl($pdf)) {
                $documents[] = [
                    'url' => $pdf,
                    'source' => 'Unpaywall',
                    'label' => 'Open PDF',
                    'isPdf' => true,
                    'score' => 100,
                ];
            }
        }

        $documents = $this->dedupeDocuments($documents);
        $best = $this->selectBestDocument($documents);
        $alternatives = [];
        foreach ($documents as $doc) {
            if ($best !== null && $doc['url'] === $best['url']) {
                continue;
            }
            $alternatives[] = $doc;
        }

        return [
            'best' => $best,
            'alternatives' => array_slice($alternatives, 0, 3),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $documents
     * @return array<string,mixed>|null
     */
    private function selectBestDocument(array $documents): ?array
    {
        if ($documents === []) {
            return null;
        }
        usort($documents, static fn(array $a, array $b): int => (int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0));
        return $documents[0];
    }

    /**
     * @param array<int,array<string,mixed>> $documents
     * @return array<int,array<string,mixed>>
     */
    private function dedupeDocuments(array $documents): array
    {
        $byUrl = [];
        foreach ($documents as $doc) {
            $url = trim((string)($doc['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $key = strtolower($url);
            if (!isset($byUrl[$key]) || (int)($doc['score'] ?? 0) > (int)($byUrl[$key]['score'] ?? 0)) {
                $byUrl[$key] = $doc;
            }
        }
        return array_values($byUrl);
    }

    private function isHttpUrl(string $value): bool
    {
        $scheme = parse_url($value, PHP_URL_SCHEME);
        return in_array($scheme, ['http', 'https'], true);
    }

    private function isPdfUrl(string $value): bool
    {
        $value = strtolower($value);
        return str_contains($value, '.pdf') || str_contains($value, '/pdf');
    }

    /**
     * @param array<string,mixed> $oa
     * @return array<string,mixed>
     */
    private function decorateOaState(array $oa, ?string $pureStatus, bool $hasExternal): array
    {
        $status = strtolower(trim((string)($oa['statusPrimary'] ?? $pureStatus ?? 'unknown')));
        $isOpen = in_array($status, ['open', 'gold', 'green', 'hybrid', 'bronze', 'oa'], true);
        $isClosed = in_array($status, ['closed', 'geschlossen'], true);

        $badgeClass = $isOpen ? 'is-open' : ($isClosed ? 'is-closed' : 'is-unknown');
        $oa['badgeClass'] = $badgeClass;
        $oa['hasExternalConflict'] = false;
        $oa['note'] = null;

        $pureStatusNormalized = strtolower(trim((string)$pureStatus));
        $externalOa = $oa['isOa'] === true;
        if ($hasExternal && $externalOa && in_array($pureStatusNormalized, ['closed', 'geschlossen'], true)) {
            $oa['hasExternalConflict'] = true;
            $oa['note'] = 'External OA version available';
        }

        return $oa;
    }

    private function formatNullableNumber(mixed $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        if (!is_numeric((string)$value)) {
            return $fallback;
        }
        return (string)$value;
    }

    private function formatDate(?string $value, string $locale, bool $withTime = false): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value);
            if ($locale === 'de_DE') {
                return $date->format($withTime ? 'd.m.Y H:i' : 'd.m.Y');
            }
            return $date->format($withTime ? 'Y-m-d H:i' : 'Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function toList(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [];
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            return [$value];
        }

        return $value;
    }
}
