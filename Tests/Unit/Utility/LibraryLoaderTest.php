<?php

declare(strict_types=1);

namespace Univie\UniviePure\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Univie\UniviePure\Utility\LibraryLoader;

/**
 * Test case for LibraryLoader
 *
 * Note: These tests require TYPO3 environment to be initialized.
 * They are marked as skipped when TYPO3 is not properly bootstrapped.
 */
class LibraryLoaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Check if TYPO3 is properly initialized
        if (!class_exists(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::class)) {
            $this->markTestSkipped('TYPO3 ExtensionManagementUtility not available');
        }
    }

    #[Test]
    public function classExists(): void
    {
        $this->assertTrue(class_exists(LibraryLoader::class));
    }

    #[Test]
    public function hasRequiredMethods(): void
    {
        $this->assertTrue(method_exists(LibraryLoader::class, 'loadCiteproc'));
        $this->assertTrue(method_exists(LibraryLoader::class, 'isCiteprocAvailable'));
        $this->assertTrue(method_exists(LibraryLoader::class, 'getCiteprocInfo'));
    }

    #[Test]
    public function isCiteprocAvailableReturnsBool(): void
    {
        // This test may fail if TYPO3 ExtensionManagementUtility is not initialized
        try {
            $result = LibraryLoader::isCiteprocAvailable();
            $this->assertIsBool($result);
        } catch (\Error $e) {
            // ExtensionManagementUtility::$packageManager not initialized
            $this->markTestSkipped('TYPO3 environment not fully initialized: ' . $e->getMessage());
        }
    }

    #[Test]
    public function loadCiteprocReturnsBoolOrThrowsException(): void
    {
        try {
            // If library is available, it should return true
            // If library is not available, it should throw RuntimeException
            if (LibraryLoader::isCiteprocAvailable()) {
                $result = LibraryLoader::loadCiteproc();
                $this->assertTrue($result);
            } else {
                $this->expectException(\RuntimeException::class);
                LibraryLoader::loadCiteproc();
            }
        } catch (\Error $e) {
            $this->markTestSkipped('TYPO3 environment not fully initialized: ' . $e->getMessage());
        }
    }

    #[Test]
    public function loadCiteprocIsIdempotent(): void
    {
        try {
            // Skip if library not available
            if (!LibraryLoader::isCiteprocAvailable()) {
                $this->markTestSkipped('citeproc-php library not available');
            }

            // First call
            $result1 = LibraryLoader::loadCiteproc();
            // Second call should also return true
            $result2 = LibraryLoader::loadCiteproc();

            $this->assertTrue($result1);
            $this->assertTrue($result2);
        } catch (\Error $e) {
            $this->markTestSkipped('TYPO3 environment not fully initialized: ' . $e->getMessage());
        }
    }

    #[Test]
    public function getCiteprocInfoReturnsArray(): void
    {
        try {
            $info = LibraryLoader::getCiteprocInfo();

            $this->assertIsArray($info);
            $this->assertArrayHasKey('installed', $info);
            $this->assertArrayHasKey('loaded', $info);
            $this->assertArrayHasKey('version', $info);
            $this->assertArrayHasKey('type', $info);
        } catch (\Error $e) {
            $this->markTestSkipped('TYPO3 environment not fully initialized: ' . $e->getMessage());
        }
    }

    #[Test]
    public function getCiteprocInfoReturnsCorrectInstalledStatus(): void
    {
        try {
            $info = LibraryLoader::getCiteprocInfo();

            $this->assertEquals(
                LibraryLoader::isCiteprocAvailable(),
                $info['installed']
            );
        } catch (\Error $e) {
            $this->markTestSkipped('TYPO3 environment not fully initialized: ' . $e->getMessage());
        }
    }
}
