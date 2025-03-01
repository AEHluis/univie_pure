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
        // Use Reflection API to override getPublicPath()
        $environmentReflection = new \ReflectionClass(Environment::class);
        $publicPathProperty = $environmentReflection->getProperty('publicPath');
        $publicPathProperty->setAccessible(true);
        $publicPathProperty->setValue(null, './Tests/Unit/Service/');
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
            ->with('GET', $this->anything())
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
     * ✅ Test `fetchApiResponse`
     */
    public function testFetchApiResponseRetrievesCachedContent(): void
    {
        $endpoint = 'testEndpoint';
        $params = ['param1' => 'value1'];
        $responseType = 'json';
        $cacheIdentifier = sha1(json_encode([$endpoint, json_encode($params), $responseType]));

        $cachedResponse = json_encode(['data' => 'cached result']);

        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->with($cacheIdentifier)
            ->willReturn($cachedResponse);

        $result = $this->webService->getAlternativeSingleResponse($endpoint, $params['param1'], $responseType);
        $this->assertEquals(['data' => 'cached result'], $result);
    }

    /**
     * ✅ Test `processResponse`
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
     * ✅ Test `getCachedContent`
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
     * ✅ Test `checkReturnCodeErrorMsg`
     */
    public function testCheckReturnCodeErrorMsgLogsErrorOn500(): void
    {
        $result = ['data' => '500', 'title' => 'Server Error'];

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Server returned error'));

        $this->invokeMethod($this->webService, 'checkReturnCodeErrorMsg', [$result]);
    }

    /**
     * ✅ Test `logAndNotify`
     */
    public function testLogAndNotifyLogsErrorAndAddsFlashMessage(): void
    {
        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->equalTo('Test Error Message'));

        $this->flashMessageServiceMock
            ->expects($this->once())
            ->method('getMessageQueueByIdentifier')
            ->willReturn($this->createMock(\TYPO3\CMS\Core\Messaging\FlashMessageQueue::class));

        $this->invokeMethod($this->webService, 'logAndNotify', ['Test Title', 'Test Error Message']);
    }

    /**
     * Helper to invoke protected/private methods.
     */
    private function invokeMethod(object $object, string $methodName,  $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

}
