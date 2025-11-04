<?php

declare(strict_types=1);

namespace Univie\UniviePure\EventListener;

use TYPO3\CMS\Core\Page\Event\BeforeJavaScriptsRenderingEvent;
use TYPO3\CMS\Core\Type\Bitmask\JsAssetPriority;

/**
 * EventListener to add JavaScript to TYPO3 backend
 * Replaces the deprecated render-preProcess hook
 */
final class BackendJavaScriptEventListener
{
    public function __invoke(BeforeJavaScriptsRenderingEvent $event): void
    {
        // Only add JavaScript in backend context
        if (!$this->isBackendContext()) {
            return;
        }

        $assetCollector = $event->getAssetCollector();

        // Add our dynamic multiselect JavaScript
        $assetCollector->addJavaScript(
            'univie_pure_backend_multiselect',
            'EXT:univie_pure/Resources/Public/JavaScript/Backend/DynamicMultiSelect.js',
            [
                'priority' => JsAssetPriority::LIBRARY
            ]
        );
    }

    /**
     * Check if we're in backend context
     */
    private function isBackendContext(): bool
    {
        // Get request from GLOBALS which is always available in TYPO3
        if (!isset($GLOBALS['TYPO3_REQUEST'])) {
            return false;
        }

        $request = $GLOBALS['TYPO3_REQUEST'];
        $applicationType = $request->getAttribute('applicationType');

        // TYPO3 v12/v13: Check for backend application type
        // In TYPO3 12+, backend is represented by SystemEnvironmentBuilder::REQUESTTYPE_BE constant
        return $applicationType === 2; // Backend
    }
}
