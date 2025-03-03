<?php
declare(strict_types=1);

namespace Univie\UniviePure\ViewHelpers\Pagination;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class UriViewHelper extends AbstractTagBasedViewHelper
{
    protected UriBuilder $uriBuilder;

    public function injectUriBuilder(UriBuilder $uriBuilder): void
    {
        $this->uriBuilder = $uriBuilder;
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('name', 'string', 'identifier important if more widgets on same page', false, 'widget');
        $this->registerArgument('arguments', 'array', 'Arguments', false, []);
        $this->registerArgument('action', 'string', 'Target action', false, null);
        $this->registerArgument('format', 'string', 'Target format', false, '');
        $this->registerArgument('addQueryStringMethod', 'string', 'Query string method', false, null);
    }

    public function render(): string
    {
        $request = $this->getRequest();
        if (!$request instanceof ServerRequestInterface) {
            return '';
        }

        $extbaseAttributes = $request->getAttribute('extbase');
        if (!$extbaseAttributes instanceof ExtbaseRequestParameters) {
            return '';
        }

        $extensionName = $extbaseAttributes->getControllerExtensionName();
        $pluginName = $extbaseAttributes->getPluginName();
        $argumentPrefix = sprintf('tx_%s_%s', strtolower($extensionName), strtolower($pluginName));
        $argumentPrefix .= '[' . $this->arguments['name'] . ']';

        $arguments = $this->arguments['arguments'];
        if ($this->hasArgument('action') && $this->arguments['action'] !== null) {
            $arguments['action'] = $this->arguments['action'];
        }
        if ($this->hasArgument('format') && $this->arguments['format'] !== '') {
            $arguments['format'] = $this->arguments['format'];
        }

        $this->uriBuilder
            ->reset()
            ->setArguments([$argumentPrefix => $arguments])
            ->setAddQueryString(true)
            ->setArgumentsToBeExcludedFromQueryString([$argumentPrefix, 'cHash']);

        if (isset($this->arguments['addQueryStringMethod'])) {
            $this->uriBuilder->setAddQueryStringMethod($this->arguments['addQueryStringMethod']);
        }

        return $this->uriBuilder->build();
    }

    protected function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}
