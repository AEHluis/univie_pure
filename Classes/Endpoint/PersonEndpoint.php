<?php

declare(strict_types=1);

namespace Univie\UniviePure\Endpoint;

/**
 * Person endpoint for Pure API
 *
 * Handles all person-related API calls.
 */
class PersonEndpoint extends AbstractEndpoint
{
    protected function getEndpointPath(): string
    {
        return '/persons';
    }

    protected function renderItem(array $item, string $view, string $locale = 'en_GB'): string
    {
        return $this->renderingService->renderPerson($item, $view, $locale);
    }
}
