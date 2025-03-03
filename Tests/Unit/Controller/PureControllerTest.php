<?php
namespace Univie\UniviePure\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Univie\UniviePure\Controller\PureController;
use Univie\UniviePure\Endpoints\DataSets;
use Univie\UniviePure\Endpoints\ResearchOutput;
use Univie\UniviePure\Endpoints\Projects;
use Univie\UniviePure\Endpoints\Equipments;
use Univie\UniviePure\Utility\LanguageUtility;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Messaging\FlashMessageService;


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
     * @var ResearchOutput|MockObject
     */
    protected $researchOutputMock;

    /**
     * @var Projects|MockObject
     */
    protected $projectsMock;

    /**
     * @var Equipments|MockObject
     */
    protected $equipmentsMock;

    /**
     * @var DataSets|MockObject
     */
    protected $dataSetsMock;

    /**
     * @var FlashMessageService|MockObject
     */
    protected $flashMessageServiceMock;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for all dependencies
        $this->configurationManagerMock = $this->createMock(ConfigurationManagerInterface::class);
        $this->researchOutputMock = $this->createMock(ResearchOutput::class);
        $this->projectsMock = $this->createMock(Projects::class);
        $this->equipmentsMock = $this->createMock(Equipments::class);
        $this->dataSetsMock = $this->createMock(DataSets::class);
        $this->flashMessageServiceMock = $this->createMock(FlashMessageService::class);

        // Create a partial mock for PureController
        $this->subject = $this->getMockBuilder(PureController::class)
            ->onlyMethods(['handleContentNotFound', 'htmlResponse', 'redirectToUri', 'getLocale'])
            ->setConstructorArgs([
                $this->configurationManagerMock,
                $this->researchOutputMock,
                $this->projectsMock,
                $this->equipmentsMock,
                $this->dataSetsMock,
                $this->flashMessageServiceMock
            ])
            ->getMock();


        // Inject the mock methods into the controller
        $reflection = new \ReflectionClass($this->subject);
        $localeProperty = $reflection->getProperty('locale');
        $localeProperty->setAccessible(true);
        $localeProperty->setValue($this->subject, 'en');


        // Check if the class_alias is already defined to avoid redeclaration
        if (!class_exists('T3luh\T3luhlib\Utils\Page', false)) {
            class_alias(
                MockPage::class, // Use the class in the current namespace
                'T3luh\T3luhlib\Utils\Page'
            );
        }
    }

    // Rest of the code remains the same...
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
     *
     * @param object $object
     * @param string $propertyName
     * @param mixed $dependency
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
        // Inject settings with a language value
        $this->inject($this->subject, 'settings', ['lang' => 'en']);

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
        $this->inject($this->subject, 'request', $request);

        // Set up a dummy global TSFE object
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->id = 123;
        $GLOBALS['TSFE']->config = ['config' => ['language' => 'en']];

        // Create a mock UriBuilder
        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->expects($this->exactly(2))
            ->method('reset')
            ->willReturnSelf();
        $uriBuilder->expects($this->once())
            ->method('setTargetPageUid')
            ->with(123)
            ->willReturnSelf();
        $uriBuilder->expects($this->once())
            ->method('setLanguage')
            ->with('en')
            ->willReturnSelf();
        $uriBuilder->expects($this->once())
            ->method('uriFor')
            ->with(
                'list',
                $this->callback(function ($arguments) {
                    // More flexible type comparison
                    return isset($arguments['filter']) &&
                        $arguments['filter'] === 'testfilter' &&
                        isset($arguments['currentPageNumber']) &&
                        (int)$arguments['currentPageNumber'] === 2 &&
                        isset($arguments['lang']);
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
        // Set up a dummy global TSFE object
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->config = ['config' => ['language' => 'en']];

        // Create test data
        $uuid = '123-test-uuid';
        $publicationData = [
            'title' => [
                'value' => 'Test Publication Title'
            ],
            // Add other publication data as needed
        ];

        // Mock the bibtex response
        $bibtexXml = new \stdClass();
        $bibtexXml->renderings = [new \stdClass()];
        $bibtexXml->renderings[0]->rendering = '@article{Test2023, title={Test Publication}}';

        // Set up ResearchOutput mock to return test data
        $this->researchOutputMock->expects($this->once())
            ->method('getBibtex')
            ->with($uuid, $this->anything())
            ->willReturn($bibtexXml);

        $this->researchOutputMock->expects($this->once())
            ->method('getSinglePublication')
            ->with($uuid)
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
        $viewMock->expects($this->once())
            ->method('assignMultiple')
            ->with($this->callback(function ($variables) use ($publicationData, $uuid) {
                return isset($variables['publication'], $variables['bibtex'], $variables['lang']) &&
                    $variables['publication'] === $publicationData &&
                    strpos($variables['bibtex'], '@article') !== false &&
                    (string)$variables['lang'] === 'en';
            }));
        $this->inject($this->subject, 'view', $viewMock);

        // Create a mock response
        $responseMock = $this->createMock(ResponseInterface::class);
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
            'initialNoResults' => 0
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

        // Set up mock publication data
        $publicationData = [
            'count' => 20,
            'offset' => 0,
            'contributionToJournal' => [
                [
                    'title' => ['value' => 'Publication 1'],
                    'uuid' => 'pub-uuid-1',
                    'authors' => [
                        ['name' => 'Author 1'],
                        ['name' => 'Author 2']
                    ]
                ],
                [
                    'title' => ['value' => 'Publication 2'],
                    'uuid' => 'pub-uuid-2',
                    'authors' => [
                        ['name' => 'Author 3'],
                        ['name' => 'Author 4']
                    ]
                ]
            ]
        ];

        // Set up ResearchOutput mock to return test data
        $this->researchOutputMock->expects($this->once())
            ->method('getPublicationList')
            ->with($this->anything(), 1, $this->anything())
            ->willReturn($publicationData);

        // Mock the view
        $viewMock = $this->createMock(\TYPO3Fluid\Fluid\View\ViewInterface::class);
        $viewMock->expects($this->once())
            ->method('assignMultiple')
            ->with($this->callback(function ($variables) {
                return isset($variables['what_to_display'], $variables['pagination'], $variables['paginator']) &&
                    $variables['what_to_display'] === 'PUBLICATIONS' &&
                    $variables['initial_no_results'] === 0;
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
}

class TestLanguageUtility extends LanguageUtility {
    public function __toString() {
        return 'en';
    }
}


// Define the mock class in the same namespace as your test
class MockPage {
    public static function updatePageTitle($title) {
        // Do nothing or add test-specific behavior
    }
}