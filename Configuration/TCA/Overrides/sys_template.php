<?php
/*
 * If you include the TypoScript this way, it will not be automatically loaded.
 * You MUST load it by adding the static include in the Web > Template module in the backend.
 */
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    'tx_univiepure',
    'Configuration/TypoScript/setup.typoscript',
    'pure: Static TS'
);
