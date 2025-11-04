<?php
declare(strict_types=1);

namespace Univie\UniviePure\Hooks;

use TYPO3\CMS\Core\Page\Event\BeforeJavaScriptsRenderingEvent;

/**
 * Event listener to add JavaScript to TYPO3 backend
 */
class BackendJavaScriptHook
{
    /**
     * Add JavaScript files to backend
     */
    public function __invoke(BeforeJavaScriptsRenderingEvent $event): void
    {
        // Only in backend context
        if (!$this->isBackendContext()) {
            return;
        }

        $pageRenderer = $event->getPageRenderer();

        // Add our dynamic multiselect JavaScript
        $pageRenderer->addJsFile(
            'EXT:univie_pure/Resources/Public/JavaScript/Backend/DynamicMultiSelect.js'
        );
    }

    /**
     * Check if we're in backend context
     */
    private function isBackendContext(): bool
    {
        // TYPO3 v12 uses integer constants for application type
        if (isset($GLOBALS['TYPO3_REQUEST'])) {
            $applicationType = $GLOBALS['TYPO3_REQUEST']->getAttribute('applicationType');
            // In TYPO3 12, backend = 2
            return $applicationType === 2;
        }

        return false;
    }
}