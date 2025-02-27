<?php
declare(strict_types = 1);
namespace Univie\UniviePure\ViewHelpers\Pagination;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class PaginateViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('objects', 'mixed', 'Array or QueryResult', true);
        $this->registerArgument('as', 'string', 'New variable name', true);
        $this->registerArgument('itemsPerPage', 'int', 'Items per page', false, 10);
        $this->registerArgument('name', 'string', 'Unique identification - will take "as" as fallback', false, '');
    }

    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): string {
        if ($arguments['objects'] === null) {
            return $renderChildrenClosure();
        }

        $templateVariableContainer = $renderingContext->getVariableProvider();
        $templateVariableContainer->add($arguments['as'], [
            'pagination' => self::getPagination($arguments),
            'paginator' => self::getPaginator($arguments),
            'name' => self::getName($arguments)
        ]);

        $output = $renderChildrenClosure();
        $templateVariableContainer->remove($arguments['as']);
        return $output;
    }

    protected static function getPagination(array $arguments): PaginationInterface
    {
        $paginator = self::getPaginator($arguments);
        return GeneralUtility::makeInstance(SimplePagination::class, $paginator);
    }

    protected static function getPaginator(array $arguments): PaginatorInterface
    {
        if (is_array($arguments['objects'])) {
            $paginatorClass = ArrayPaginator::class;
        } elseif ($arguments['objects'] instanceof QueryResultInterface) {
            $paginatorClass = QueryResultPaginator::class;
        } else {
            throw new \InvalidArgumentException(
                'Given object is not supported for pagination',
                1634132847
            );
        }

        return GeneralUtility::makeInstance(
            $paginatorClass,
            $arguments['objects'],
            self::getPageNumber($arguments),
            $arguments['itemsPerPage']
        );
    }

    protected static function getPageNumber(array $arguments): int
    {
        $currentPage = 1;
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        if ($request instanceof ServerRequestInterface
            && ApplicationType::fromRequest($request)->isFrontend()
        ) {
            $queryParams = $request->getQueryParams();
            $paginationName = self::getName($arguments);
            if (isset($queryParams[$paginationName]['currentPageNumber'])) {
                $currentPage = (int)$queryParams[$paginationName]['currentPageNumber'];
            }
        }

        return max(1, $currentPage);
    }

    protected static function getName(array $arguments): string
    {
        return $arguments['name'] ?: $arguments['as'];
    }
}
