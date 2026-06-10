<?php

declare(strict_types=1);

namespace Univie\UniviePure\Endpoint;

/**
 * Organizational unit endpoint for Pure API
 *
 * Handles all organizational-unit-related API calls.
 * Note: OpenAPI uses 'organizations' for organizational units.
 */
class OrganizationalUnitEndpoint extends AbstractEndpoint
{
    protected function getEndpointPath(): string
    {
        return '/organizations';
    }

    protected function renderItem(array $item, string $view, string $locale = 'en_GB'): string
    {
        return $this->renderingService->renderOrganisation($item, $view, $locale);
    }
}
