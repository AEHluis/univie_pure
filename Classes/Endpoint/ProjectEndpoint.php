<?php

declare(strict_types=1);

namespace Univie\UniviePure\Endpoint;

/**
 * Project endpoint for Pure API
 *
 * Handles all project-related API calls.
 */
class ProjectEndpoint extends AbstractEndpoint
{
    protected function getEndpointPath(): string
    {
        return '/projects';
    }

    protected function renderItem(array $item, string $view, string $locale = 'en_GB'): string
    {
        return $this->renderingService->renderProject($item, $view, $locale);
    }
}
