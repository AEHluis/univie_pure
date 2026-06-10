<?php
/**
 * Autoloader for bundled citeproc-php library
 *
 * This file provides PSR-4 autoloading for the citeproc-php library
 * and its dependencies when bundled within the TYPO3 extension.
 */

// Prevent double loading
if (class_exists('Seboettg\\CiteProc\\CiteProc', false)) {
    return;
}

$basePath = dirname(__DIR__);

// PSR-4 autoloader
spl_autoload_register(function ($class) use ($basePath) {
    // Namespace prefix => base directory mappings
    $prefixes = [
        'Seboettg\\CiteProc\\' => $basePath . '/src/',
        'Seboettg\\Collection\\' => $basePath . '/vendor/seboettg/collection/src/',
        'MyCLabs\\Enum\\' => $basePath . '/vendor/myclabs/php-enum/src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        // Check if the class uses this namespace prefix
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        // Get the relative class name
        $relativeClass = substr($class, $len);

        // Replace namespace separators with directory separators
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }

    return false;
});

// Load functions file (required by citeproc-php)
$functionsFile = $basePath . '/src/functions.php';
if (file_exists($functionsFile)) {
    require_once $functionsFile;
}

// Load collection functions (required by seboettg/collection)
$collectionFunctionsFile = $basePath . '/vendor/seboettg/collection/src/ArrayList/Functions.php';
if (file_exists($collectionFunctionsFile)) {
    require_once $collectionFunctionsFile;
}

// Load vendorPath helper
$vendorPathFile = $basePath . '/vendorPath.php';
if (file_exists($vendorPathFile)) {
    require_once $vendorPathFile;
}
