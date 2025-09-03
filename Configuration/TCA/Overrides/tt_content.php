<?php
defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

// Register plugin
ExtensionUtility::registerPlugin(
    'UniviePure',
    'UniviePure',
    'LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:tt_content.list_type.univiepure_univiepure'
);

$pluginSignature = 'univiepure_univiepure';

// Add FlexForm configuration
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature] = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    $pluginSignature,
    'FILE:EXT:univie_pure/Configuration/FlexForms/flexform.xml'
);