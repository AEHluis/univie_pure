<?php

declare(strict_types=1);

namespace Univie\UniviePure\Utility;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Library Loader for Bundled PHP Libraries
 *
 * Loads citeproc-php from Libraries/ directory at extension root.
 * The library is bundled directly with the extension for easier distribution.
 */
class LibraryLoader
{
    /**
     * @var bool Whether the library has been loaded
     */
    private static bool $loaded = false;

    /**
     * Load bundled citeproc-php library
     *
     * @return void
     */
    public static function loadCiteProc(): void
    {
        if (self::$loaded) {
            return;
        }

        // Check if already loaded (e.g., via Composer in development)
        if (class_exists(\Seboettg\CiteProc\CiteProc::class)) {
            self::$loaded = true;
            return;
        }

        $extensionPath = GeneralUtility::getFileAbsFileName(
            'EXT:univie_pure/Libraries/citeproc-php/'
        );

        // Try to load Composer autoloader from bundled library
        $autoloadFile = $extensionPath . 'vendor/autoload.php';

        if (file_exists($autoloadFile)) {
            require_once $autoloadFile;
            self::$loaded = true;
            return;
        }

        // Fallback: Register manual PSR-4 autoloader
        self::registerAutoloader($extensionPath);
        self::$loaded = true;
    }

    /**
     * Register PSR-4 autoloader for citeproc-php
     *
     * @param string $basePath Base path to the library
     * @return void
     */
    private static function registerAutoloader(string $basePath): void
    {
        spl_autoload_register(function ($class) use ($basePath) {
            // citeproc-php namespace
            $prefix = 'Seboettg\\CiteProc\\';
            $len = strlen($prefix);

            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file = $basePath . 'src/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });

        // Also register dependencies if needed
        $dependenciesPath = $basePath . 'vendor/';
        if (is_dir($dependenciesPath)) {
            self::registerDependencies($dependenciesPath);
        }
    }

    /**
     * Register autoloaders for library dependencies
     *
     * @param string $vendorPath Path to vendor directory
     * @return void
     */
    private static function registerDependencies(string $vendorPath): void
    {
        // Register common dependencies used by citeproc-php
        $autoloaders = [
            'Seboettg\\Collection\\' => $vendorPath . 'seboettg/collection/src/',
        ];

        foreach ($autoloaders as $prefix => $path) {
            if (!is_dir($path)) {
                continue;
            }

            spl_autoload_register(function ($class) use ($prefix, $path) {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    return;
                }

                $relativeClass = substr($class, $len);
                $file = $path . str_replace('\\', '/', $relativeClass) . '.php';

                if (file_exists($file)) {
                    require_once $file;
                }
            });
        }
    }

    /**
     * Check if citeproc-php is available
     *
     * @return bool
     */
    public static function isCiteProcAvailable(): bool
    {
        self::loadCiteProc();
        return class_exists(\Seboettg\CiteProc\CiteProc::class);
    }
}
