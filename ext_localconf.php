<?php
defined('TYPO3') || die();

use Univie\UniviePure\Controller\PureController;
use Univie\UniviePure\Controller\PaginateController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Core\Environment;
use Psr\Log\LogLevel;

call_user_func(
    function () {
        // Register plugin
        ExtensionUtility::configurePlugin(
            'UniviePure',
            'UniviePure',
            [
                PureController::class => 'list,listHandler,show',
                PaginateController::class => 'index,paginate',
            ],
            // non-cacheable actions
            [
                PureController::class => 'list,listHandler,show',
                PaginateController::class => 'index,paginate',
            ]
        );

        // Add PageTSConfig for wizard
        ExtensionManagementUtility::addPageTSConfig(
            '@import "EXT:univie_pure/Configuration/TSconfig/Page/Mod/Wizards/NewContentElement.tsconfig"'
        );

        // Cache configuration
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['univie_pure'])) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['univie_pure'] = [
                'frontend' => VariableFrontend::class,
                'backend' => FileBackend::class,
                'options' => [
                    'defaultLifetime' => 86400 // 24 hours
                ],
                'groups' => ['pages', 'all']
            ];
        }

        // Configure logging
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['Univie']['UniviePure']['writerConfiguration'] = [
            LogLevel::ERROR => [
                FileWriter::class => [
                    'logFile' => Environment::getVarPath() . '/log/univie_pure_error.log'
                ]
            ]
        ];
    }
);
