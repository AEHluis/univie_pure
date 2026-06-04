<?php

declare(strict_types=1);

namespace Univie\UniviePure\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Library loader for bundled third-party libraries
 *
 * Handles loading of bundled citeproc-php library and its dependencies.
 */
class LibraryLoader
{
    private static bool $citeprocLoaded = false;

    /**
     * Load the citeproc-php library
     *
     * This method is idempotent - calling it multiple times has no effect.
     *
     * @return bool True if library was loaded successfully
     * @throws \RuntimeException If library files are not found
     */
    public static function loadCiteproc(): bool
    {
        if (self::$citeprocLoaded) {
            return true;
        }

        // Check if already loaded via composer
        if (class_exists(\Seboettg\CiteProc\CiteProc::class, false)) {
            self::$citeprocLoaded = true;
            return true;
        }

        $autoloadFile = self::getAutoloadPath();

        if (!file_exists($autoloadFile)) {
            throw new \RuntimeException(
                sprintf(
                    'Citeproc-php library not found at %s. ' .
                    'Please ensure the library is bundled in Libraries/citeproc-php/',
                    $autoloadFile
                )
            );
        }

        require_once $autoloadFile;
        self::$citeprocLoaded = true;

        return true;
    }

    /**
     * Check if citeproc-php is available
     *
     * @return bool True if library is available (either bundled or via composer)
     */
    public static function isCiteprocAvailable(): bool
    {
        // Already loaded
        if (class_exists(\Seboettg\CiteProc\CiteProc::class, false)) {
            return true;
        }

        // Check bundled version
        return file_exists(self::getAutoloadPath());
    }

    /**
     * Get the path to the bundled autoload file
     *
     * @return string Absolute path to autoload.php
     */
    private static function getAutoloadPath(): string
    {
        return GeneralUtility::getFileAbsFileName(
            'EXT:univie_pure/Libraries/citeproc-php/vendor/autoload.php'
        );
    }

    /**
     * Get citeproc-php version info
     *
     * @return array Version information
     */
    public static function getCiteprocInfo(): array
    {
        $composerFile = GeneralUtility::getFileAbsFileName(
            'EXT:univie_pure/Libraries/citeproc-php/composer.json'
        );

        $info = [
            'installed' => self::isCiteprocAvailable(),
            'loaded' => self::$citeprocLoaded,
            'version' => 'unknown',
            'type' => 'unknown',
        ];

        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);
            $info['version'] = $composerData['version'] ?? '2.x (bundled)';
            $info['type'] = 'bundled';
        }

        // Check if loaded via main composer
        if (class_exists(\Seboettg\CiteProc\CiteProc::class, false) && !self::$citeprocLoaded) {
            $info['type'] = 'composer';
        }

        return $info;
    }
}
