<?php
declare(strict_types=1);

namespace Univie\UniviePure\Hooks;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;

/**
 * Hook to add JavaScript to TYPO3 backend
 */
class BackendJavaScriptHook
{
    /**
     * Add JavaScript files to backend
     */
    public function addJavaScript(array $params, PageRenderer $pageRenderer): void
    {
        // Only in backend context
        if (!$this->isBackendContext()) {
            return;
        }

        // Register AJAX URLs in TYPO3.settings.ajaxUrls
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        try {
            $pageRenderer->addInlineSettingArray('ajaxUrls', [
                'univie_pure_search_organizations' => (string)$uriBuilder->buildUriFromRoute('univie_pure_search_organizations'),
                'univie_pure_search_persons_with_org' => (string)$uriBuilder->buildUriFromRoute('univie_pure_search_persons_with_org'),
                'univie_pure_search_projects' => (string)$uriBuilder->buildUriFromRoute('univie_pure_search_projects'),
            ]);
        } catch (\Exception $e) {
            // Routes might not be registered yet during cache clear - JavaScript fallback URLs will be used
        }

        // Add our dynamic multiselect JavaScript
        $pageRenderer->addJsFile(
            'EXT:univie_pure/Resources/Public/JavaScript/Backend/DynamicMultiSelect.js',
            'text/javascript',
            false,  // compress
            false,  // force on top
            '',     // all wrap
            true,   // exclude from concatenation
            '|',    // split char
            false,  // async
            'backend'  // type
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

        // Fallback for older TYPO3 versions
        return defined('TYPO3_MODE') && TYPO3_MODE === 'BE';
    }
}
