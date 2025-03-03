<?php

declare(strict_types=1);

namespace Univie\UniviePure\Tests\Service;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use Univie\UniviePure\Service\WebService;
use TYPO3\CMS\Core\Http\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Univie\UniviePure\Utility\DotEnv;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use PHPUnit\Framework\MockObject\Rule\AnyInvokedCount as AnyInvokedCountMatcher;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;


class WebServiceTest extends TestCase
{
    private WebService $webService;
    private $clientMock;
    private $requestFactoryMock;
    private $streamFactoryMock;
    private $cacheMock;
    private $flashMessageServiceMock;
    private $loggerMock;
    private $extensionConfigurationMock;

    protected function setUp(): void
    {
        // Use Reflection API to set Environment publicPath
        // Updated to avoid using deprecated setAccessible
        if (PHP_VERSION_ID >= 80100) {
            // PHP 8.1+ approach
            $publicPathReflection = new \ReflectionProperty(Environment::class, 'publicPath');
            $publicPathReflection->setValue(null, './Tests/Unit/Service/');
        } else {
            // For older PHP versions
            $environmentReflection = new \ReflectionClass(Environment::class);
            $publicPathProperty = $environmentReflection->getProperty('publicPath');
            $publicPathProperty->setAccessible(true);
            $publicPathProperty->setValue(null, './Tests/Unit/Service/');
        }

        $this->clientMock = $this->createMock(ClientInterface::class);
        $this->requestFactoryMock = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactoryMock = $this->createMock(StreamFactoryInterface::class);
        $this->cacheMock = $this->createMock(FrontendInterface::class);
        $this->flashMessageServiceMock = $this->createMock(FlashMessageService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);

        $this->webService = new WebService(
            $this->clientMock,
            $this->requestFactoryMock,
            $this->streamFactoryMock,
            $this->cacheMock,
            $this->flashMessageServiceMock,
            $this->loggerMock,
            $this->extensionConfigurationMock
        );
    }

    public function testGetAlternativeSingleResponseHandlesApiRequest(): void
    {
        $endpoint = 'testEndpoint';
        $query = 'testQuery';
        $responseType = 'json';
        $lang = 'de_DE';

        // Create a valid URI
        $uri = new Uri('https://mock-server.com/mock-endpoint');

        // Create a proper request mock that implements RequestInterface
        $requestMock = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        // Configure request mock with necessary methods
        $requestMock->method('withHeader')
            ->willReturnSelf();

        // Configure requestFactory to return our properly typed request
        $this->requestFactoryMock
            ->method('createRequest')
            ->with('GET', $this->isType('object'))
            ->willReturn($requestMock);

        // Mock response and stream
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getBody')->willReturn($streamMock);
        $streamMock->method('__toString')->willReturn(json_encode(['data' => 'api response']));

        // Configure client to accept our request mock and return response
        $this->clientMock
            ->method('sendRequest')
            ->with($requestMock)
            ->willReturn($responseMock);

        $result = $this->webService->getAlternativeSingleResponse($endpoint, $query, $responseType, $lang);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('api response', $result['data']);
    }

    /**
     * Test `fetchApiResponse` with cached content
     */
    public function testFetchApiResponseRetrievesCachedContent(): void
    {
        $endpoint = 'testEndpoint';
        $q = 'value1';
        $responseType = 'json';
        $lang = 'de_DE';

        // Use the actual method from WebService to generate the cache identifier
        $cacheIdentifier = $this->webService->generateCacheIdentifier($endpoint, json_encode(['q' => $q, 'locale' => $lang]), $responseType);

        $cachedResponse = json_encode(['data' => 'cached result']);

        // Set up cache mock to return our cached response
        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->with($cacheIdentifier)
            ->willReturn($cachedResponse);

        $result = $this->webService->getAlternativeSingleResponse($endpoint, $q, $responseType, $lang);

        // Add assertions to prevent "risky test" warning
        $this->assertIsArray($result);
        $this->assertEquals(['data' => 'cached result'], $result);
    }

    /**
     * Test `processResponse`
     */
    public function testProcessResponseHandlesJson(): void
    {
        $jsonContent = json_encode(['message' => 'Success']);
        $result = $this->invokeMethod($this->webService, 'processResponse', [$jsonContent, 'json', true]);

        $this->assertIsArray($result);
        $this->assertEquals(['message' => 'Success'], $result);
    }

    public function testProcessResponseHandlesXml(): void
    {
        $xmlContent = '<?xml version="1.0"?><response><message>Success</message></response>';
        $result = $this->invokeMethod($this->webService, 'processResponse', [$xmlContent, 'xml', true]);

        $this->assertIsArray($result);
        $this->assertEquals(['message' => 'Success'], $result);
    }

    /**
     * Test `getCachedContent`
     */
    public function testGetCachedContentReturnsNullWhenCacheMisses(): void
    {
        $cacheIdentifier = 'testCacheKey';

        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->with($cacheIdentifier)
            ->willReturn(false);

        $result = $this->invokeMethod($this->webService, 'getCachedContent', [$cacheIdentifier]);
        $this->assertNull($result);
    }

    /**
     * Test `checkReturnCodeErrorMsg`
     */
    public function testCheckReturnCodeErrorMsgLogsErrorOn500(): void
    {
        // Create a result array that will trigger the error condition
        $result = ['data' => '500', 'title' => 'Server Error'];

        // Expect the logger's error method to be called
        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with('The server returned an error response.', $this->isType('array'));

        // Expect the flash message service to be used
        $queueMock = $this->createMock(\TYPO3\CMS\Core\Messaging\FlashMessageQueue::class);
        $queueMock->expects($this->once())
            ->method('enqueue')
            ->with($this->isInstanceOf(\TYPO3\CMS\Core\Messaging\FlashMessage::class));

        $this->flashMessageServiceMock
            ->expects($this->once())
            ->method('getMessageQueueByIdentifier')
            ->willReturn($queueMock);

        // Call the method using reflection
        $this->invokeMethod($this->webService, 'checkReturnCodeErrorMsg', [$result]);
    }

    /**
     * Test `logAndNotify`
     */
    public function testLogAndNotifyLogsErrorAndAddsFlashMessage(): void
    {
        // Create a mock queue
        $queueMock = $this->createMock(FlashMessageQueue::class);
        // For void methods, we don't set a return value, just expect it to be called
        $queueMock->expects($this->once())
            ->method('enqueue')
            ->with($this->isInstanceOf(\TYPO3\CMS\Core\Messaging\FlashMessage::class));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with('Test Error Message', $this->isType('array'));

        $this->flashMessageServiceMock
            ->expects($this->once())
            ->method('getMessageQueueByIdentifier')
            ->willReturn($queueMock);

        $this->invokeMethod($this->webService, 'logAndNotify', [
            'Test Title',
            'Test Error Message',
            []  // Just pass an empty array for logContext, as the method now accepts ContextualFeedbackSeverity directly
        ]);
        // Add assertion to prevent "risky test" warning
        $this->assertTrue(true);
    }

    /**
     * Helper to invoke protected/private methods.
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = [])
    {
        if (PHP_VERSION_ID >= 80100) {
            // PHP 8.1+ approach
            $reflection = new \ReflectionClass(get_class($object));
            $method = $reflection->getMethod($methodName);
            return $method->invokeArgs($object, $parameters);
        } else {
            // For older PHP versions
            $reflection = new \ReflectionClass(get_class($object));
            $method = $reflection->getMethod($methodName);
            $method->setAccessible(true);
            return $method->invokeArgs($object, $parameters);
        }
    }
}