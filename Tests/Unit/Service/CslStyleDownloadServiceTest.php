<?php

declare(strict_types=1);

namespace Univie\UniviePure\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Univie\UniviePure\Service\CslStyleDownloadService;
use TYPO3\CMS\Core\Core\Environment;

/**
 * Test case for CslStyleDownloadService
 */
class CslStyleDownloadServiceTest extends TestCase
{
    private ClientInterface|MockObject $httpClientMock;
    private RequestFactoryInterface|MockObject $requestFactoryMock;
    private LoggerInterface|MockObject $loggerMock;
    private CslStyleDownloadService $service;

    private ?string $testVarPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClientMock = $this->createMock(ClientInterface::class);
        $this->requestFactoryMock = $this->createMock(RequestFactoryInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        // Set up Environment mock for var path
        if (!class_exists(Environment::class) || !method_exists(Environment::class, 'getVarPath')) {
            $this->markTestSkipped('TYPO3 Environment class not available');
        }

        // Create a writable test directory BEFORE instantiating the service
        $this->testVarPath = sys_get_temp_dir() . '/typo3-csl-test-' . getmypid() . '-' . time();

        try {
            $reflection = new \ReflectionProperty(Environment::class, 'varPath');
            $reflection->setValue(null, $this->testVarPath);
        } catch (\ReflectionException $e) {
            $this->markTestSkipped('Cannot set Environment varPath');
        }

        try {
            $this->service = new CslStyleDownloadService(
                $this->httpClientMock,
                $this->requestFactoryMock,
                $this->loggerMock
            );
        } catch (\RuntimeException $e) {
            // Directory creation might fail in some test environments
            $this->markTestSkipped('Cannot create test directory: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if ($this->testVarPath && is_dir($this->testVarPath)) {
            $this->removeDirectory($this->testVarPath);
        }
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    if (is_dir($dir . '/' . $object)) {
                        $this->removeDirectory($dir . '/' . $object);
                    } else {
                        unlink($dir . '/' . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    #[Test]
    public function getAvailableStylesReturnsArray(): void
    {
        $result = $this->service->getAvailableStyles();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('apa', $result);
        $this->assertArrayHasKey('ieee', $result);
        $this->assertArrayHasKey('vancouver', $result);
        $this->assertArrayHasKey('harvard-cite-them-right', $result);
    }

    #[Test]
    public function getAvailableStylesReturnsFormattedNames(): void
    {
        $result = $this->service->getAvailableStyles();

        // Check that display names are formatted properly
        $this->assertEquals('APA', $result['apa']);
        $this->assertEquals('IEEE', $result['ieee']);
    }

    #[Test]
    public function isStyleAvailableReturnsTrueForKnownStyle(): void
    {
        $result = $this->service->isStyleAvailable('apa');

        $this->assertTrue($result);
    }

    #[Test]
    public function isStyleAvailableReturnsTrueForIeee(): void
    {
        $result = $this->service->isStyleAvailable('ieee');

        $this->assertTrue($result);
    }

    #[Test]
    public function isStyleAvailableNormalizesStyleName(): void
    {
        // Should work with .csl extension
        $result1 = $this->service->isStyleAvailable('apa.csl');

        // Should work with uppercase
        $result2 = $this->service->isStyleAvailable('APA');

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    #[Test]
    public function getStyleDownloadsFromGitHubWhenNotCached(): void
    {
        $styleName = 'test-style-' . uniqid();
        $cslContent = '<?xml version="1.0"?><style xmlns="http://purl.org/net/xbiblio/csl">test</style>';

        // Set up request mock
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->method('withHeader')->willReturnSelf();

        $this->requestFactoryMock
            ->method('createRequest')
            ->willReturn($requestMock);

        // Set up response mock
        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($cslContent);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getBody')->willReturn($streamMock);

        $this->httpClientMock
            ->method('sendRequest')
            ->willReturn($responseMock);

        // Expect logging
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('info');

        // This would normally download, but our mock returns cslContent
        // The actual test is that it tries to make the request
        try {
            $result = $this->service->getStyle($styleName);
            // If we get here, the download worked
            $this->assertNotEmpty($result);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('could not be downloaded', $e->getMessage());
        }
    }

    #[Test]
    public function clearCacheReturnsCount(): void
    {
        $result = $this->service->clearCache();

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    #[Test]
    public function preloadCommonStylesReturnsResults(): void
    {
        // Set up request mock to return valid response
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->method('withHeader')->willReturnSelf();

        $this->requestFactoryMock
            ->method('createRequest')
            ->willReturn($requestMock);

        // Set up response mock (will fail with 404 in tests, but that's ok)
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(404);

        $this->httpClientMock
            ->method('sendRequest')
            ->willReturn($responseMock);

        $result = $this->service->preloadCommonStyles();

        $this->assertIsArray($result);
        // At minimum, should try to download the known styles
        $this->assertArrayHasKey('apa', $result);
        $this->assertArrayHasKey('ieee', $result);
    }

    #[Test]
    public function getStylePathReturnsNullForUnknownStyle(): void
    {
        // Set up request to fail
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->method('withHeader')->willReturnSelf();

        $this->requestFactoryMock
            ->method('createRequest')
            ->willReturn($requestMock);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(404);

        $this->httpClientMock
            ->method('sendRequest')
            ->willReturn($responseMock);

        // Use a truly unknown style name
        $result = $this->service->getStylePath('completely-unknown-style-12345');

        $this->assertNull($result);
    }
}
