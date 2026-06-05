<?php

declare(strict_types=1);

namespace Univie\UniviePure\Endpoint;

/**
 * Equipment endpoint for Pure API
 *
 * Handles all equipment-related API calls and data normalization.
 */
class EquipmentEndpoint extends AbstractEndpoint
{
    protected function getEndpointPath(): string
    {
        return '/equipment';
    }

    protected function renderItem(array $item, string $view, string $locale = 'en_GB'): string
    {
        return $this->renderingService->renderEquipment($item, $view, $locale);
    }

    /**
     * {@inheritdoc}
     *
     * Override to add equipment-specific view fields.
     */
    public function getAll(array $params = []): array
    {
        $result = parent::getAll($params);

        foreach ($result['items'] as &$item) {
            $this->normalizeItemForView($item);
        }
        unset($item);

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * Override to add equipment-specific view fields.
     */
    public function getOne(string $uuid, array $params = []): ?array
    {
        $item = parent::getOne($uuid, $params);

        if ($item !== null) {
            $this->normalizeItemForView($item);
        }

        return $item;
    }

    /**
     * Normalize equipment item for view templates.
     *
     * Extracts contact persons, emails, web addresses from the API response
     * structure into simple arrays for easy template consumption.
     */
    private function normalizeItemForView(array &$item): void
    {
        $item['contactPerson'] = $this->extractPersonNames($item['persons'] ?? []);
        $item['email'] = $this->extractEmails($item);
        $item['webAddress'] = $this->extractWebAddresses($item);
        $item['portalUrl'] = is_string($item['portalUrl'] ?? null) ? $item['portalUrl'] : '';
    }

    /**
     * Extract person names from the persons array.
     */
    private function extractPersonNames(array $persons): array
    {
        $names = [];
        foreach ($persons as $person) {
            if (!is_array($person)) {
                continue;
            }
            $name = $person['name'] ?? null;
            if (is_array($name)) {
                $fullName = trim(($name['firstName'] ?? '') . ' ' . ($name['lastName'] ?? ''));
                if ($fullName !== '') {
                    $names[] = $fullName;
                }
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * Extract email addresses from the equipment item.
     */
    private function extractEmails(array $item): array
    {
        $emails = [];

        // Check emails array
        foreach ($item['emails'] ?? [] as $email) {
            if (is_string($email) && $email !== '') {
                $emails[] = $email;
            } elseif (is_array($email) && !empty($email['value'])) {
                $emails[] = $email['value'];
            }
        }

        // Check electronicAddresses array
        foreach ($item['electronicAddresses'] ?? [] as $addr) {
            if (is_array($addr) && str_contains($addr['type']['uri'] ?? '', 'email')) {
                $value = $addr['value'] ?? '';
                if ($value !== '') {
                    $emails[] = $value;
                }
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Extract web addresses from the equipment item.
     */
    private function extractWebAddresses(array $item): array
    {
        $urls = [];

        // Check links array
        foreach ($item['links'] ?? [] as $link) {
            if (is_string($link) && $link !== '') {
                $urls[] = $link;
            } elseif (is_array($link) && !empty($link['url'])) {
                $urls[] = $link['url'];
            }
        }

        // Check webAddresses array
        foreach ($item['webAddresses'] ?? [] as $addr) {
            if (is_array($addr)) {
                if (!empty($addr['value']['value'])) {
                    $urls[] = $addr['value']['value'];
                } elseif (!empty($addr['value']) && is_string($addr['value'])) {
                    $urls[] = $addr['value'];
                }
            }
        }

        return array_values(array_unique($urls));
    }
}
