<?php

namespace Univie\UniviePure\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Tests\Functional\Fixtures\MockWebService;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\StreamFactory;
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
     * Create a MockWebService with necessary dependencies
     *
     * @return MockWebService
     */
    protected function createMockWebService(): MockWebService
    {
        // Create proper mocks for HTTP components
        $streamMock = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $streamMock->method('__toString')
            ->willReturn(json_encode(['status' => 'success', 'data' => []]));

        $responseMock = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $responseMock->method('getStatusCode')
            ->willReturn(200);
        $responseMock->method('getBody')
            ->willReturn($streamMock);

        // Configure the request mock with method chaining
        $requestMock = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $requestMock->method('withHeader')->willReturn($requestMock);
        $requestMock->method('withBody')->willReturn($requestMock);

        // Configure client mock to return the response mock
        $this->clientMock->method('sendRequest')
            ->willReturn($responseMock);

        // Configure request factory to return the request mock
        $requestFactoryMock = $this->createMock(RequestFactoryInterface::class);
        $requestFactoryMock->method('createRequest')
            ->willReturn($requestMock);

        // Create stream factory mock
        $streamFactoryMock = $this->createMock(StreamFactoryInterface::class);
        $streamFactoryMock->method('createStream')
            ->willReturn($streamMock);

        $flashMessageServiceMock = $this->createMock(FlashMessageService::class);
        $loggerMock = $this->createMock(LoggerInterface::class);
        $extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $extensionConfigurationMock->method('get')
            ->willReturn(['settings' => ['chooseSelector' => 'default']]);

        // Create and configure MockWebService
        $webServiceMock = new MockWebService(
            $this->clientMock,
            $requestFactoryMock,
            $streamFactoryMock,
            $this->cacheMock,
            $flashMessageServiceMock,
            $loggerMock,
            $extensionConfigurationMock
        );

        return $webServiceMock;
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