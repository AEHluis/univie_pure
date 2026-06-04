<?php

namespace Univie\UniviePure\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Univie\UniviePure\Controller\PureController;
use Univie\UniviePure\Service\ApiServiceInterface;
use Univie\UniviePure\Service\CslRenderingService;
use Univie\UniviePure\Utility\LanguageUtility;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use Univie\UniviePure\Service\Enrichment\PublicationInsightsService;

/**
 * Test case for class PureController.
 */
class PureControllerTest extends UnitTestCase
{
    /**
     * @var PureController|MockObject
     */
    protected $subject;

    /**
     * @var ConfigurationManagerInterface|MockObject
     */
    protected $configurationManagerMock;

    /**
     * @var FlashMessageService|MockObject
     */
    protected $flashMessageServiceMock;

    /**
     * @var PublicationInsightsService|MockObject
     */
    protected $publicationInsightsServiceMock;

    /**
     * @var ApiServiceInterface|MockObject
     */
    protected $apiServiceMock;

    /**
     * @var CslRenderingService|MockObject
     */
    protected $cslRenderingServiceMock;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for all dependencies
        $this->configurationManagerMock = $this->createMock(ConfigurationManagerInterface::class);
        $this->flashMessageServiceMock = $this->createMock(FlashMessageService::class);
        $this->publicationInsightsServiceMock = $this->createMock(PublicationInsightsService::class);
        $this->apiServiceMock = $this->createMock(ApiServiceInterface::class);
        $this->cslRenderingServiceMock = $this->createMock(CslRenderingService::class);

        // Create a partial mock for PureController
        $this->subject = $this->getMockBuilder(PureController::class)
            ->onlyMethods(['handleContentNotFound', 'htmlResponse', 'redirectToUri', 'getLocale', 'getLocaleShort'])
            ->setConstructorArgs([
                $this->configurationManagerMock,
                $this->apiServiceMock,
                $this->publicationInsightsServiceMock,
                $this->cslRenderingServiceMock
            ])
            ->getMock();

        // Set up the getLocale and getLocaleShort methods to return 'en'
        $this->subject->method('getLocale')->willReturn('en');
        $this->subject->method('getLocaleShort')->willReturn('en');

        // Inject the locale and localeShort properties into the controller
        $reflection = new \ReflectionClass($this->subject);

        $localeProperty = $reflection->getProperty('locale');
        $localeProperty->setAccessible(true);
        $localeProperty->setValue($this->subject, 'en');

        if ($reflection->hasProperty('localeShort')) {
            $localeShortProperty = $reflection->getProperty('localeShort');
            $localeShortProperty->setAccessible(true);
            $localeShortProperty->setValue($this->subject, 'en');
        }

        // Check if the class_alias is already defined to avoid redeclaration
        if (!class_exists('T3luh\T3luhlib\Utils\Page', false)) {
            class_alias(
                MockPage::class,
                'T3luh\T3luhlib\Utils\Page'
            );
        }
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void
    {
        unset($this->subject);
        parent::tearDown();
    }

    /**
     * Helper method to inject a dependency into a protected property.
     */
    protected function inject($object, string $propertyName, $dependency): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $dependency);
    }

    /**
     * Test that listHandlerAction builds the correct URI from the request arguments and calls redirectToUri.
     */
    #[Test]
    public function listHandlerActionRedirectsToUri(): void
    {
        // Create a mock request object
        $request = $this->createMock(Request::class);
        $request->expects($this->any())
            ->method('hasArgument')
            ->willReturnMap([
                ['filter', true],
                ['currentPageNumber', true],
            ]);
        $request->expects($this->any())
            ->method('getArgument')
            ->willReturnMap([
                ['filter', 'TestFilter'],
                ['currentPageNumber', '2'],
            ]);
        $routing = new class {
            public function getPageId(): int
            {
                return 123;
            }
        };
        $request->expects($this->once())
            ->method('getAttribute')
            ->with('routing')
            ->willReturn($routing);
        $this->inject($this->subject, 'request', $request);

        // Create a mock UriBuilder
        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->expects($this->once())
            ->method('reset')
            ->willReturnSelf();
        $uriBuilder->expects($this->once())
            ->method('setTargetPageUid')
            ->with(123)
            ->willReturnSelf();
        $uriBuilder->expects($this->once())
            ->method('uriFor')
            ->with(
                'list',
                $this->callback(function ($arguments) {
                    return isset($arguments['filter']) &&
                        $arguments['filter'] === 'testfilter' &&
                        isset($arguments['currentPageNumber']) &&
                        (int)$arguments['currentPageNumber'] === 2;
                }),
                'Pure'
            )
            ->willReturn('dummyUri');
        $this->inject($this->subject, 'uriBuilder', $uriBuilder);

        // Create a mock response
        $responseMock = $this->createMock(RedirectResponse::class);

        // Expect redirectToUri to be called with the dummy URI and return the mock response
        $this->subject->expects($this->once())
            ->method('redirectToUri')
            ->with('dummyUri')
            ->willReturn($responseMock);

        // Execute the action
        $result = $this->subject->listHandlerAction();

        // Assert that the result is the expected response
        $this->assertSame($responseMock, $result);
    }

    /**
     * Test that listHandlerAction preserves umlauts in sanitized filter values.
     */
    #[Test]
    public function listHandlerActionPreservesUmlautsInFilter(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->any())
            ->method('hasArgument')
            ->willReturnMap([
                ['filter', true],
                ['currentPageNumber', false],
            ]);
        $request->expects($this->once())
            ->method('getArgument')
            ->with('filter')
            ->willReturn('Höll');
        $routing = new class {
            public function getPageId(): int
            {
                return 123;
            }
        };
        $request->expects($this->once())
            ->method('getAttribute')
            ->with('routing')
            ->willReturn($routing);
        $this->inject($this->subject, 'request', $request);

        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->expects($this->once())
            ->method('reset')
            ->willReturnSelf();
        $uriBuilder->expects($this->once())
            ->method('setTargetPageUid')
            ->with(123)
            ->willReturnSelf();
        $uriBuilder->expects($this->once())
            ->method('uriFor')
            ->with(
                'list',
                $this->callback(function ($arguments) {
                    return isset($arguments['filter']) &&
                        $arguments['filter'] === 'höll' &&
                        isset($arguments['currentPageNumber']) &&
                        (int)$arguments['currentPageNumber'] === 1;
                }),
                'Pure'
            )
            ->willReturn('dummyUri');
        $this->inject($this->subject, 'uriBuilder', $uriBuilder);

        $responseMock = $this->createMock(RedirectResponse::class);
        $this->subject->expects($this->once())
            ->method('redirectToUri')
            ->with('dummyUri')
            ->willReturn($responseMock);

        $result = $this->subject->listHandlerAction();

        $this->assertSame($responseMock, $result);
    }

    /**
     * Test that listAction with an unknown "what_to_display" setting calls handleContentNotFound.
     */
    #[Test]
    public function listActionUnknownDisplayCallsHandleContentNotFound(): void
    {
        // Inject settings with an unknown what_to_display
        $this->inject($this->subject, 'settings', [
            'what_to_display' => 'UNKNOWN',
            'pageSize' => 20,
            'initialNoResults' => 0,
        ]);

        // Set up a dummy global TSFE object
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->id = 123;
        $GLOBALS['TSFE']->config = ['config' => ['language' => 'en']];

        // Define $_GET['filter'] to avoid warnings
        $_GET['filter'] = '';

        // Create a dummy request
        $request = $this->createMock(Request::class);
        $request->method('hasArgument')->willReturn(false);
        $this->inject($this->subject, 'request', $request);

        // Mock the view
        $viewMock = $this->createMock(\TYPO3Fluid\Fluid\View\ViewInterface::class);
        $viewMock->method('assign')->willReturnSelf();
        $this->inject($this->subject, 'view', $viewMock);

        // Expect handleContentNotFound to be called
        $this->subject->expects($this->once())
            ->method('handleContentNotFound')
            ->willThrowException(new ImmediateResponseException(new Response(), 1591428020));

        // Create a mock response for htmlResponse
        $responseMock = $this->createMock(ResponseInterface::class);
        $this->subject->method('htmlResponse')->willReturn($responseMock);

        // Execute the action with exception handling
        $this->expectException(ImmediateResponseException::class);
        $this->subject->listAction();
    }

    /**
     * Test that showAction without a "what2show" argument calls handleContentNotFound.
     */
    #[Test]
    public function showActionWithoutWhat2showCallsHandleContentNotFound(): void
    {
        // Set up a dummy global TSFE object
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->config = ['config' => ['language' => 'en']];

        // Create a dummy request
        $request = $this->createMock(Request::class);
        $request->method('getArguments')->willReturn([]);
        $this->inject($this->subject, 'request', $request);

        // Mock the view
        $viewMock = $this->createMock(\TYPO3Fluid\Fluid\View\ViewInterface::class);
        $viewMock->method('assign')->willReturnSelf();
        $this->inject($this->subject, 'view', $viewMock);

        // Expect handleContentNotFound to be called
        $this->subject->expects($this->once())
            ->method('handleContentNotFound')
            ->willThrowException(new ImmediateResponseException(new Response(), 1591428020));

        // Create a mock response for htmlResponse
        $responseMock = $this->createMock(ResponseInterface::class);
        $this->subject->method('htmlResponse')->willReturn($responseMock);

        // Execute the action with exception handling
        $this->expectException(ImmediateResponseException::class);
        $this->subject->showAction();
    }

    /**
     * Test that showAction with a "what2show" argument different from 'publ' calls handleContentNotFound.
     */
    #[Test]
    public function showActionWithNonPublWhat2showCallsHandleContentNotFound(): void
    {
        // Set up a dummy global TSFE object
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->config = ['config' => ['language' => 'en']];

        // Create a dummy request
        $request = $this->createMock(Request::class);
        $request->method('getArguments')->willReturn(['what2show' => 'other']);
        $this->inject($this->subject, 'request', $request);

        // Mock the view
        $viewMock = $this->createMock(\TYPO3Fluid\Fluid\View\ViewInterface::class);
        $viewMock->method('assign')->willReturnSelf();
        $this->inject($this->subject, 'view', $viewMock);

        // Expect handleContentNotFound to be called
        $this->subject->expects($this->once())
            ->method('handleContentNotFound')
            ->willThrowException(new ImmediateResponseException(new Response(), 1591428020));

        // Create a mock response for htmlResponse
        $responseMock = $this->createMock(ResponseInterface::class);
        $this->subject->method('htmlResponse')->willReturn($responseMock);

        // Execute the action with exception handling
        $this->expectException(ImmediateResponseException::class);
        $this->subject->showAction();
    }

    /**
     * Test that showAction with valid 'publ' what2show and UUID returns a response.
     */
    #[Test]
    public function showActionWithValidPublicationReturnsResponse(): void
    {
        // Enable singleton reset to avoid test isolation issues
        $this->resetSingletonInstances = true;

        // Set up a dummy global TSFE object
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->config = ['config' => ['language' => 'en']];

        // Create test data
        $uuid = '123-test-uuid';
        $publicationData = [
            'title' => [
                'value' => 'Test Publication Title'
            ],
        ];

        // Set up ApiService mock to return test data
        $this->apiServiceMock->expects($this->once())
            ->method('getResearchOutput')
            ->with($uuid, $this->anything())
            ->willReturn($publicationData);

        // Create a dummy request with valid arguments
        $request = $this->createMock(Request::class);
        $request->method('getArguments')->willReturn([
            'what2show' => 'publ',
            'uuid' => $uuid
        ]);
        $this->inject($this->subject, 'request', $request);

        // Mock the view
        $viewMock = $this->createMock(\TYPO3Fluid\Fluid\View\ViewInterface::class);
        $viewMock->method('assign')->willReturnSelf();
        $viewMock->expects($this->once())
            ->method('assignMultiple')
            ->with($this->callback(function ($variables) use ($publicationData, $uuid) {
                return isset($variables['publication']) &&
                    isset($variables['citationStyles']) &&
                    isset($variables['publicationUuid']) &&
                    isset($variables['lang']) &&
                    $variables['publication'] === $publicationData &&
                    $variables['publicationUuid'] === $uuid &&
                    is_array($variables['citationStyles']) &&
                    (string)$variables['lang'] === 'en';
            }));
        $this->inject($this->subject, 'view', $viewMock);

        // Create a mock response with withHeader method
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('withHeader')->willReturnSelf();
        $this->subject->method('htmlResponse')->willReturn($responseMock);

        // Execute the action
        $result = $this->subject->showAction();

        // Assert that the result is the expected response
        $this->assertSame($responseMock, $result);
    }

    /**
     * Test that listAction with PUBLICATIONS display type returns the expected response.
     */
    #[Test]
    public function listActionWithPublicationsReturnsExpectedResponse(): void
    {
        // Inject settings for publications
        $this->inject($this->subject, 'settings', [
            'what_to_display' => 'PUBLICATIONS',
            'pageSize' => 10,
            'initialNoResults' => 0,
            'citationStyle' => 'apa'
        ]);

        // Set up a dummy global TSFE object
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->id = 123;
        $GLOBALS['TSFE']->config = ['config' => ['language' => 'en']];

        // Define $_GET to avoid warnings
        $_GET = [];
        $_GET['tx_univiepure_univiepure']['currentPageNumber'] = 1;

        // Create a dummy request
        $request = $this->createMock(Request::class);
        $request->method('hasArgument')->willReturn(false);
        $this->inject($this->subject, 'request', $request);

        // Set up mock publication data from OpenAPI
        $publicationResponse = [
            'count' => 20,
            'items' => [
                [
                    'uuid' => 'pub-uuid-1',
                    'title' => ['value' => 'Publication 1'],
                    'rendering' => '<div>Publication 1 rendered</div>',
                    'publicationYear' => 2024
                ],
                [
                    'uuid' => 'pub-uuid-2',
                    'title' => ['value' => 'Publication 2'],
                    'rendering' => '<div>Publication 2 rendered</div>',
                    'publicationYear' => 2023
                ]
            ]
        ];

        // Set up ApiService mock to return test data
        $this->apiServiceMock->expects($this->once())
            ->method('getResearchOutputs')
            ->with($this->anything())
            ->willReturn($publicationResponse);

        // Mock the view
        $viewMock = $this->createMock(\TYPO3Fluid\Fluid\View\ViewInterface::class);
        $viewMock->expects($this->once())
            ->method('assignMultiple')
            ->with($this->callback(function ($variables) {
                return isset($variables['what_to_display'], $variables['pagination'], $variables['paginator']) &&
                    $variables['what_to_display'] === 'PUBLICATIONS';
            }));
        $this->inject($this->subject, 'view', $viewMock);

        // Create a mock response
        $responseMock = $this->createMock(ResponseInterface::class);
        $this->subject->method('htmlResponse')->willReturn($responseMock);

        // Execute the action
        $result = $this->subject->listAction();

        // Assert that the result is the expected response
        $this->assertSame($responseMock, $result);
    }

    /**
     * Test that getCitationStylesMetadata returns CSL styles.
     */
    #[Test]
    public function getCitationStylesMetadataReturnsCslStyles(): void
    {
        // Use reflection to call the private method
        $reflection = new \ReflectionClass($this->subject);
        $method = $reflection->getMethod('getCitationStylesMetadata');

        $result = $method->invoke($this->subject);

        // Check that CSL styles are included
        $styleIds = array_column($result, 'id');
        $this->assertContains('apa', $styleIds);
        $this->assertContains('ieee', $styleIds);
        $this->assertContains('chicago-author-date', $styleIds);

        // Check that CSL styles have isCsl flag
        $cslStyles = array_filter($result, fn($s) => isset($s['isCsl']) && $s['isCsl'] === true);
        $this->assertNotEmpty($cslStyles);
    }
}

class TestLanguageUtility extends LanguageUtility
{
    public function __toString()
    {
        return 'en';
    }
}

class MockPage
{
    public static function updatePageTitle($title)
    {
        // Do nothing
    }
}
