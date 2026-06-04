<?php

declare(strict_types=1);

namespace Univie\UniviePure\Endpoint;

/**
 * Organizational unit endpoint for Pure API
 *
 * Handles all organizational-unit-related API calls.
 * Note: OpenAPI uses 'organizational-units' (American spelling)
 */
class OrganizationalUnitEndpoint extends AbstractEndpoint
{
    protected function getEndpointPath(): string
    {
        return '/organizational-units';
    }

    protected function renderItem(array $item, string $view): string
    {
        return $this->renderingService->renderOrganisation($item, $view);
    }
}
