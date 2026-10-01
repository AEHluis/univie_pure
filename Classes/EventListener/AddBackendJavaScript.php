<?php

declare(strict_types=1);

namespace Univie\UniviePure\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Page\Event\BeforeJavaScriptsRenderingEvent;

/**
 * Loads the dynamic Pure search for FlexForm multiselects in the backend.
 * Replaces the PageRenderer "render-preProcess" hook removed in TYPO3 v14.
 * The AJAX route URLs are provided by the backend PageRenderer in TYPO3.settings.ajaxUrls.
 */
#[AsEventListener('univie-pure/add-backend-javascript')]
final class AddBackendJavaScript
{
    public function __invoke(BeforeJavaScriptsRenderingEvent $event): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($event->isInline() || $event->isPriority() || $request === null || !ApplicationType::fromRequest($request)->isBackend()) {
            return;
        }
        $event->getAssetCollector()->addJavaScript(
            'univie-pure-dynamic-multiselect',
            'EXT:univie_pure/Resources/Public/JavaScript/Backend/DynamicMultiSelect.js'
        );
    }
}
