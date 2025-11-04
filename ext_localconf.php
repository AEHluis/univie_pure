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
        // Load bundled PHP libraries (citeproc-php for CSL citation rendering)
        LibraryLoader::loadCiteProc();
        // Register plugin
        ExtensionUtility::configurePlugin(
            'UniviePure',
            'UniviePure',
            [
                PureController::class => 'list,listHandler,show',
            ],
            // non-cacheable actions
            [
                PureController::class => 'list,listHandler,show',
            ]
        );

        // TypoScript
        ExtensionManagementUtility::addTypoScriptConstants(
            '@import "EXT:univie_pure/Configuration/TypoScript/constants.typoscript"'
        );
        ExtensionManagementUtility::addTypoScriptSetup(
            '@import "EXT:univie_pure/Configuration/TypoScript/setup.typoscript"'
        );

        // Add PageTSConfig for wizard
        ExtensionManagementUtility::addPageTSConfig(
            '@import "EXT:univie_pure/Configuration/TSconfig/Page/Mod/Wizards/NewContentElement.tsconfig"'
        );

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
