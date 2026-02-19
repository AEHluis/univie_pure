<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\Enrichment;

class DoiExtractor
{
    /**
     * @return string[]
     */
    public function extractFromPublication(array $publication): array
    {
        $dois = [];
        $electronicVersions = $publication['electronicVersions'] ?? [];

        foreach ($this->toList($electronicVersions) as $version) {
            if (!is_array($version)) {
                continue;
            }

            foreach (['doi', 'link'] as $field) {
                $raw = trim((string)($version[$field] ?? ''));
                if ($raw === '') {
                    continue;
                }

                $normalized = $this->normalizeDoi($raw);
                if ($normalized !== null) {
                    $dois[$normalized] = true;
                }
            }
        }

        return array_keys($dois);
    }

    public function selectPrimaryDoi(array $dois): ?string
    {
        foreach ($dois as $doi) {
            if (stripos($doi, '10.48550/arxiv.') !== 0) {
                return $doi;
            }
        }

        return $dois[0] ?? null;
    }

    public function normalizeDoi(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $value) ?? $value;
        $value = preg_replace('#^doi:\s*#i', '', $value) ?? $value;
        $value = trim($value);

        if (preg_match('#(10\.\d{4,9}/[-._;()/:A-Za-z0-9]+)#', $value, $matches) !== 1) {
            return null;
        }

        return strtolower(rtrim($matches[1], ".,; "));
    }

    /**
     * @return array<int, mixed>
     */
    private function toList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        if ($value === []) {
            return [];
        }

        // Single object-like array from XML/JSON conversion
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            if (isset($value['doi']) || isset($value['link'])) {
                return [$value];
            }

            if (isset($value['electronicVersion'])) {
                return $this->toList($value['electronicVersion']);
            }
        }

        return $value;
    }
}

