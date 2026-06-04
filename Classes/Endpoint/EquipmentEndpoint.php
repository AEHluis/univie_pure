<?php

declare(strict_types=1);

namespace Univie\UniviePure\Endpoint;

/**
 * Equipment endpoint for Pure API
 *
 * Handles all equipment-related API calls.
 */
class EquipmentEndpoint extends AbstractEndpoint
{
    protected function getEndpointPath(): string
    {
        return '/equipments';
    }

    protected function renderItem(array $item, string $view): string
    {
        return $this->renderingService->renderEquipment($item, $view);
    }
}
