<?php
defined('TYPO3') || die('Access denied.');

use Univie\UniviePure\Controller\PureController;
use Univie\UniviePure\Controller\PaginateController;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$pageRenderer = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Page\PageRenderer::class);
$pageRenderer->loadRequireJsModule('TYPO3/CMS/UniviePure/CustomMultipleSideBySide');

call_user_func(
    function () {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'Univie.UniviePure',
            'UniviePure',
            [
                PureController::class => 'list,listHandler,show',
                PaginateController::class => 'index,paginate'
            ],
            // non-cacheable actions
            [
                PureController::class => 'list,listHandler,show',
                PaginateController::class  => 'index,paginate'
            ]
        );

        // wizards
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
            'mod {
                wizards.newContentElement.wizardItems.plugins {
                    elements {
                        listoffers {
                            iconIdentifier = univie_pure-plugin    
                            title = LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.title
                            description = LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.description
                            tt_content_defValues {
                                CType = list
                                list_type = univiepure_univiepure
                            }
                        }
                    }
                    show = *
                }               
           }'
        );
        $iconRegistry = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);

        $iconRegistry->registerIcon(
            'univie_pure-plugin',
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            ['source' => 'EXT:univie_pure/Resources/Public/Icons/fis.svg']
        );

    }
);
