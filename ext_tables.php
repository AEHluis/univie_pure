<?php
defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// Register plugin
ExtensionUtility::registerPlugin(
    'UniviePure',
    'UniviePure',
    'T3LUH FIS'
);

// Plugin signature should be defined directly
$pluginSignature = 'univiepure_univiepure';

// Add FlexForm configuration
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature] = 'pi_flexform';
ExtensionManagementUtility::addPiFlexFormValue(
    $pluginSignature,
    'FILE:EXT:univie_pure/Configuration/FlexForms/flexform.xml'
);