<?php
defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

// Register plugin with FlexForm (v14: pass as 7th param so showitem is set automatically)
ExtensionUtility::registerPlugin(
    'UniviePure',
    'UniviePure',
    'LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xlf:tt_content.list_type.univiepure_univiepure',
    null,
    'plugins',
    '',
    'FILE:EXT:univie_pure/Configuration/FlexForms/flexform.xml'
);