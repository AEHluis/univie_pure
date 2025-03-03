<?php
// Define line feed constant if not already defined
if (!defined('LF')) {
    define('LF', chr(10));
}

// Ensure autoloader is loaded
$autoloadPath = __DIR__ . '/../.Build/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die('Autoloader not found. Please run composer install.');
}
require_once $autoloadPath;

// Initialize testing framework
$testbase = new \TYPO3\TestingFramework\Core\Testbase();
$testbase->defineOriginalRootPath();
$testbase->createDirectory(ORIGINAL_ROOT . '.Build/public/typo3temp/var/tests');
$testbase->createDirectory(ORIGINAL_ROOT . '.Build/var/tests');

// Simulate backend user for testing
$GLOBALS['BE_USER'] = (object)[
    'uc' => ['lang' => 'de']
];

// Set testing context
if (!defined('TYPO3_CONTEXT')) {
    define('TYPO3_CONTEXT', 'Testing');
}
