<?php

declare(strict_types=1);

namespace Univie\UniviePure\Endpoint;

/**
 * DataSet endpoint for Pure API
 *
 * Handles all data-set-related API calls.
 */
class DataSetEndpoint extends AbstractEndpoint
{
    protected function getEndpointPath(): string
    {
        return '/data-sets';
    }

    protected function renderItem(array $item, string $view): string
    {
        return $this->renderingService->renderDataSet($item, $view);
    }
}
