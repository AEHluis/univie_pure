<?php
defined('TYPO3') || die();

use Univie\UniviePure\Controller\PureController;
use Univie\UniviePure\Utility\LibraryLoader;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Core\Environment;
use Psr\Log\LogLevel;

call_user_func(
    function () {
        // Load bundled citeproc-php library for CSL citation rendering
        // This must be done early to ensure the autoloader is registered
        if (LibraryLoader::isCiteprocAvailable()) {
            LibraryLoader::loadCiteproc();
        }
        // Register plugin
        ExtensionUtility::configurePlugin(
            'UniviePure',
            'UniviePure',
            [
                PureController::class => 'list,listHandler,show',
            ],
            // non-cacheable actions
            // Note: list and show are now cacheable to reduce Pure API load
            // listHandler remains non-cacheable as it handles form POST and redirects
            [
                PureController::class => 'listHandler',
            ]
        );

        // Add PageTSConfig for wizard (v14: defaultPageTSconfig statt addPageTSConfig)
        $GLOBALS['TYPO3_CONF_VARS']['BE']['defaultPageTSconfig'] ??= '';
        $GLOBALS['TYPO3_CONF_VARS']['BE']['defaultPageTSconfig'] .= "\n@import 'EXT:univie_pure/Configuration/TSconfig/Page/Mod/Wizards/NewContentElement.tsconfig'";

        // Cache configuration
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['univie_pure'])) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['univie_pure'] = [
                'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
                'backend' => \TYPO3\CMS\Core\Cache\Backend\FileBackend::class,
                'options' => [
                    'defaultLifetime' => 86400 // 24 hours
                ],
                'groups' => ['univie_pure', 'all']
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
