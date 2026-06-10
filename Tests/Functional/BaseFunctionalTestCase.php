<?php

namespace Univie\UniviePure\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Base test case for all functional tests in univie_pure extension
 */
abstract class BaseFunctionalTestCase extends FunctionalTestCase
{
    /**
     * Extensions that should be loaded for this test
     *
     * @var array<string>
     */
    protected array $testExtensionsToLoad = [
        'univie_pure',
        'georgringer/numbered-pagination'
    ];

    /**
     * Core extensions required for testing
     *
     * @var array<string>
     */
    protected array $coreExtensionsToLoad = [
        'core',
        'frontend',
        'backend',
        'extbase',
        'fluid',
    ];

    /**
     * @var FrontendInterface|MockObject
     */
    protected $cacheMock;

    /**
     * @var ClientInterface|MockObject
     */
    protected $clientMock;

    /**
     * Set up for functional tests
     */
    protected function setUp(): void
    {
        // Ensure all required extensions are loaded
        $this->loadExtensions();

        parent::setUp();

        // Set up cache mock to avoid real caching
        $this->cacheMock = $this->createMock(FrontendInterface::class);
        $this->cacheMock->method('get')->willReturn(false);
        $this->cacheMock->method('set')->willReturn(null);

        // Set up HTTP client mock to avoid real API calls
        $this->clientMock = $this->createMock(ClientInterface::class);

        // Add a property to the settings to avoid the "chooseSelector" warning
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['univie_pure']['settings']['chooseSelector'] = 'default';
    }

    /**
     * Create a mock JSON response with the given data
     *
     * @param array $data The data to encode as JSON
     * @return ResponseInterface
     */
    protected function createJsonResponse(array $data): ResponseInterface
    {
        $streamMock = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $streamMock->method('__toString')
            ->willReturn(json_encode($data));

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')
            ->willReturn(200);
        $responseMock->method('getBody')
            ->willReturn($streamMock);

        return $responseMock;
    }

    /**
     * Calculate offset for pagination
     *
     * @param int $pageSize The page size
     * @param int $currentPage The current page number
     * @return int The calculated offset
     */
    protected function calculateOffset(int $pageSize, int $currentPage): int
    {
        return ($currentPage - 1) * $pageSize;
    }

    /**
     * Load extensions for testing
     */
    protected function loadExtensions(): void
    {
        // Combine core and test extensions
        $this->testExtensionsToLoad = array_merge(
            $this->testExtensionsToLoad,
            $this->coreExtensionsToLoad
        );
    }
}
