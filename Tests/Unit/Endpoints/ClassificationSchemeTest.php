<?php
declare(strict_types=1);

namespace Univie\UniviePure\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Univie\UniviePure\Utility\ClassificationScheme;
use Univie\UniviePure\Service\WebService;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;


final class ClassificationSchemeTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Use class_alias to replace CommonUtilities with our mock
        if (!class_exists('Univie\UniviePure\Utility\CommonUtilities', false)) {
            class_alias(
                CommonUtilitiesMock::class,
                'Univie\UniviePure\Utility\CommonUtilities'
            );
        }

        // Mock the cache frontend
        $cacheFrontendMock = $this->createMock(FrontendInterface::class);
        $cacheFrontendMock->method('has')->willReturn(false);

        // Mock the cache manager
        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->willReturn($cacheFrontendMock);

        // Register the cache manager mock in GeneralUtility
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);

        // Mock the WebService
        $webServiceMock = $this->createMock(WebService::class);
        GeneralUtility::addInstance(WebService::class, $webServiceMock);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    #[Test]
    public function getOrganisationsReturnsExpectedItems(): void
    {
        // Create test data
        $organisationsData = [
            'items' => [
                [
                    'uuid' => '123-abc',
                    'name' => [
                        'text' => [
                            [
                                'value' => 'Test Organization'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Create mock for WebService
        $webServiceMock = $this->createMock(WebService::class);
        $webServiceMock->expects($this->once())
            ->method('getJson')
            ->with('organisational-units', $this->anything())
            ->willReturn($organisationsData);

        // Create mock for cache
        $cacheFrontendMock = $this->createMock(FrontendInterface::class);
        $cacheFrontendMock->method('has')->willReturn(false);
        $cacheFrontendMock->method('get')->willReturn(null);

        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->willReturn($cacheFrontendMock);

        // Set up the singletons and instances
        GeneralUtility::purgeInstances();
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);
        GeneralUtility::addInstance(WebService::class, $webServiceMock);

        // Create a partial mock of ClassificationScheme to control the cache behavior
        $subject = $this->getMockBuilder(ClassificationScheme::class)
            ->onlyMethods(['getOrganisationsFromCache', 'isValidOrganisationsData', 'storeOrganisationsToCache'])
            ->getMock();

        // Configure the mock to simulate cache miss
        $subject->method('getOrganisationsFromCache')->willReturn(null);
        $subject->method('isValidOrganisationsData')->willReturn(true);
        $subject->method('storeOrganisationsToCache')->willReturnSelf();

        // Prepare config array to be filled
        $config = ['items' => []];

        // Call the method
        $subject->getOrganisations($config);

        // Assert the result
        $this->assertCount(1, $config['items']);
        $this->assertEquals('Test Organization', $config['items'][0][0]);
        $this->assertEquals('123-abc', $config['items'][0][1]);
    }

    #[Test]
    public function getPersonsReturnsExpectedItems(): void
    {
        // Create test data
        $personsData = [
            'items' => [
                [
                    'uuid' => '456-def',
                    'name' => [
                        'firstName' => 'John',
                        'lastName' => 'Doe'
                    ]
                ]
            ]
        ];

        // Create mock for WebService
        $webServiceMock = $this->createMock(WebService::class);
        $webServiceMock->expects($this->once())
            ->method('getJson')
            ->with('persons', $this->anything())
            ->willReturn($personsData);

        // Create mock for cache
        $cacheFrontendMock = $this->createMock(FrontendInterface::class);
        $cacheFrontendMock->method('has')->willReturn(false);
        $cacheFrontendMock->method('get')->willReturn(null);

        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->willReturn($cacheFrontendMock);

        // Set up the singletons and instances
        GeneralUtility::purgeInstances();
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);
        GeneralUtility::addInstance(WebService::class, $webServiceMock);

        // Create a partial mock of ClassificationScheme to control the cache behavior
        $subject = $this->getMockBuilder(ClassificationScheme::class)
            ->onlyMethods(['getPersonsFromCache', 'isValidPersonsData', 'storePersonsToCache'])
            ->getMock();

        // Configure the mock to simulate cache miss
        $subject->method('getPersonsFromCache')->willReturn(null);
        $subject->method('isValidPersonsData')->willReturn(false);
        $subject->method('storePersonsToCache'); // No return value for void methods

        // Prepare config array to be filled
        $config = ['items' => []];

        // Call the method
        $subject->getPersons($config);

        // Assert the result
        $this->assertCount(1, $config['items']);
        $this->assertEquals('Doe, John', $config['items'][0][0]);
        $this->assertEquals('456-def', $config['items'][0][1]);
    }


    #[Test]
    public function getProjectsReturnsExpectedItems(): void
    {
        // Create test data
        $projectsData = [
            'items' => [
                [
                    'uuid' => '789-ghi',
                    'acronym' => 'TEST',
                    'title' => [
                        'text' => [
                            [
                                'value' => 'Test Project'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Create mock for WebService
        $webServiceMock = $this->createMock(WebService::class);
        $webServiceMock->expects($this->once())
            ->method('getJson')
            ->with('projects', $this->anything())
            ->willReturn($projectsData);

        // Create mock for cache
        $cacheFrontendMock = $this->createMock(FrontendInterface::class);
        $cacheFrontendMock->method('has')->willReturn(false);
        $cacheFrontendMock->method('get')->willReturn(null);

        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->willReturn($cacheFrontendMock);

        // Set up the singletons and instances
        GeneralUtility::purgeInstances();
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);
        GeneralUtility::addInstance(WebService::class, $webServiceMock);

        // Create a partial mock of ClassificationScheme to control the cache behavior
        $subject = $this->getMockBuilder(ClassificationScheme::class)
            ->onlyMethods(['getProjectsFromCache', 'isValidProjectsData', 'storeProjectsToCache'])
            ->getMock();

        // Configure the mock to simulate cache miss
        $subject->method('getProjectsFromCache')->willReturn(null);
        $subject->method('isValidProjectsData')->willReturn(false);
        $subject->method('storeProjectsToCache'); // No return value for void methods

        // Prepare config array to be filled
        $config = ['items' => []];

        // Call the method
        $subject->getProjects($config);

        // Assert the result
        $this->assertCount(1, $config['items']);
        $this->assertEquals('TEST - Test Project', $config['items'][0][0]);
        $this->assertEquals('789-ghi', $config['items'][0][1]);
    }

    #[Test]
    public function getTypesFromPublicationsReturnsExpectedItems(): void
    {
        // Create test data
        $publicationTypesData = [
            'items' => [
                [
                    'containedClassifications' => [
                        [
                            'uri' => '/test/parent1',
                            'term' => [
                                'text' => [
                                    [
                                        'value' => 'Parent 1'
                                    ]
                                ]
                            ],
                            'classificationRelations' => [
                                [
                                    'relationType' => [
                                        'uri' => '/dk/atira/pure/core/hierarchies/child'
                                    ],
                                    'relatedTo' => [
                                        0 => [
                                            'uri' => '/test/child1'
                                        ],
                                        'uri' => '/test/child1',
                                        'term' => [
                                            'text' => [
                                                [
                                                    'value' => 'Child 1'
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Create mock for WebService
        $webServiceMock = $this->createMock(WebService::class);
        $webServiceMock->expects($this->once())
            ->method('getJson')
            ->with('classification-schemes', $this->anything())
            ->willReturn($publicationTypesData);

        // Create mock for cache
        $cacheFrontendMock = $this->createMock(FrontendInterface::class);
        $cacheFrontendMock->method('has')->willReturn(false);
        $cacheFrontendMock->method('get')->willReturn(null);

        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->willReturn($cacheFrontendMock);

        // Set up the singletons and instances
        GeneralUtility::purgeInstances();
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);
        GeneralUtility::addInstance(WebService::class, $webServiceMock);

        // Create a partial mock of ClassificationScheme to control the cache and sorting behavior
        $subject = $this->getMockBuilder(ClassificationScheme::class)
            ->onlyMethods(['getTypesFromPublicationsFromCache', 'isValidPublicationTypesData', 'storeTypesFromPublicationsToCache', 'sortClassification'])
            ->getMock();

        // Configure the mock to simulate cache miss and return sorted data
        $subject->method('getTypesFromPublicationsFromCache')->willReturn(null);
        $subject->method('isValidPublicationTypesData')->willReturn(false);
        $subject->method('storeTypesFromPublicationsToCache'); // No return value for void methods
        $subject->method('sortClassification')->willReturn([
            [
                'title' => 'Parent Category',
                'uri' => '/test/parent',
                'child' => [
                    [
                        'title' => 'Child Item',
                        'uri' => '/test/child'
                    ]
                ]
            ]
        ]);

        // Prepare config array to be filled
        $config = ['items' => []];

        // Call the method
        $subject->getTypesFromPublications($config);

        // Assert the result
        $this->assertCount(2, $config['items']);
        $this->assertEquals('----- Parent Category: -----', $config['items'][0][0]);
        $this->assertEquals('--div--', $config['items'][0][1]);
        $this->assertEquals('Child Item', $config['items'][1][0]);
        $this->assertEquals('/test/child', $config['items'][1][1]);
    }


    #[Test]
    public function sortClassificationReturnsCorrectStructure(): void
    {
        $subject = new ClassificationScheme();

        $unsortedData = [
            'items' => [
                [
                    'containedClassifications' => [
                        [
                            'uri' => '/test/parent1',
                            'term' => [
                                'text' => [
                                    [
                                        'value' => 'Parent 1'
                                    ]
                                ]
                            ],
                            'classificationRelations' => [
                                [
                                    'relationType' => [
                                        'uri' => '/dk/atira/pure/core/hierarchies/child'
                                    ],
                                    'relatedTo' => [
                                        0 => [
                                            'uri' => '/test/child1'
                                        ],
                                        'uri' => '/test/child1',
                                        'term' => [
                                            'text' => [
                                                [
                                                    'value' => 'Child 1'
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $subject->sortClassification($unsortedData);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('/test/parent1', $result[0]['uri']);
        $this->assertEquals('Parent 1', $result[0]['title']);
    }

    #[Test]
    public function getUuidForEmailReturnsExpectedUuid(): void
    {
        // Create mock for WebService that returns predefined XML response
        $webServiceMock = $this->createMock(WebService::class);
        $webServiceMock->expects($this->once())
            ->method('getXml')
            ->with('persons', $this->stringContains('test@example.com'))
            ->willReturn([
                'count' => 1,
                'person' => [
                    '@attributes' => [
                        'uuid' => '789-ghi'
                    ]
                ]
            ]);

        // Create mock for cache
        $cacheFrontendMock = $this->createMock(FrontendInterface::class);

        // Mock the cache manager
        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')
            ->with('univie_pure')
            ->willReturn($cacheFrontendMock);

        // Set up the singletons and instances
        GeneralUtility::purgeInstances();
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);
        GeneralUtility::addInstance(WebService::class, $webServiceMock);

        // Create the class under test
        $subject = new ClassificationScheme();

        // Call the method
        $result = $subject->getUuidForEmail('test@example.com');

        // Assert the result
        $this->assertEquals('789-ghi', $result);
    }


    #[Test]
    public function getUuidForEmailReturnsDefaultUuidWhenPersonNotFound(): void
    {
        // Create mock for WebService that returns no results
        $webServiceMock = $this->createMock(WebService::class);
        $webServiceMock->method('getXml')->willReturn([
            'count' => 0
        ]);
        GeneralUtility::addInstance(WebService::class, $webServiceMock);

        // Create the class under test
        $subject = new ClassificationScheme();

        // Call the method
        $result = $subject->getUuidForEmail('nonexistent@example.com');

        // Assert the result
        $this->assertEquals('123456789', $result);
    }

    #[Test]
    public function getItemsToChooseReturnsExpectedItems(): void
    {
        // Mock language service
        $GLOBALS['LANG'] = new class {
            public function sL($key) {
                return 'Translated: ' . $key;
            }
        };

        // Create the class under test
        $subject = new ClassificationScheme();

        // Prepare config array to be filled with required structure
        $config = [
            'items' => [],
            'flexParentDatabaseRow' => [
                'pi_flexform' => [
                    'data' => [
                        'sDEF' => [
                            'lDEF' => [
                                'settings.what_to_display' => [
                                    'vDEF' => []
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Prepare parameters
        $PA = [];

        // Call the method
        $subject->getItemsToChoose($config, $PA);

        // Assert the result
        $this->assertCount(3, $config['items']);
        $this->assertEquals('Translated: LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectBlank', $config['items'][0][0]);
        $this->assertEquals(-1, $config['items'][0][1]);
        $this->assertEquals('Translated: LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByUnit', $config['items'][1][0]);
        $this->assertEquals(0, $config['items'][1][1]);
        $this->assertEquals('Translated: LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByPerson', $config['items'][2][0]);
        $this->assertEquals(1, $config['items'][2][1]);
    }


    #[Test]
    public function getItemsToChooseAddsProjectOptionForPublications(): void
    {
        // Mock language service
        $GLOBALS['LANG'] = new class {
            public function sL($key) {
                return 'Translated: ' . $key;
            }
        };

        // Create the class under test
        $subject = new ClassificationScheme();

        // Prepare config array to be filled
        $config = [
            'items' => [],
            'flexParentDatabaseRow' => [
                'pi_flexform' => [
                    'data' => [
                        'sDEF' => [
                            'lDEF' => [
                                'settings.what_to_display' => [
                                    'vDEF' => ['PUBLICATIONS']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Prepare parameters
        $PA = [];

        // Call the method
        $subject->getItemsToChoose($config, $PA);

        // Assert the result
        $this->assertCount(4, $config['items']);
        $this->assertEquals('Translated: LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByProject', $config['items'][3][0]);
        $this->assertEquals(2, $config['items'][3][1]);
    }

    #[Test]
    public function getOrganisationsUsesCache(): void
    {
        // Create cache data
        $cachedData = [
            'items' => [
                [
                    'uuid' => 'cached-uuid',
                    'name' => [
                        'text' => [
                            [
                                'value' => 'Cached Organization'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Create mock for cache that indicates cache hit
        $cacheFrontendMock = $this->createMock(FrontendInterface::class);
        $cacheFrontendMock->method('has')->willReturn(true);
        $cacheFrontendMock->method('get')->willReturn($cachedData);

        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->willReturn($cacheFrontendMock);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);

        // WebService should not be called if cache is used
        $webServiceMock = $this->createMock(WebService::class);
        $webServiceMock->expects($this->never())->method('getJson');
        GeneralUtility::addInstance(WebService::class, $webServiceMock);

        // Create the class under test
        $subject = new ClassificationScheme();

        // Prepare config array to be filled
        $config = ['items' => []];

        // Call the method
        $subject->getOrganisations($config);

        // Assert the result contains cached data
        $this->assertCount(1, $config['items']);
        $this->assertEquals('Cached Organization', $config['items'][0][0]);
        $this->assertEquals('cached-uuid', $config['items'][0][1]);
    }

    #[Test]
    public function getPersonsUsesCache(): void
    {
        // Create cache data
        $cachedData = [
            'items' => [
                [
                    'uuid' => 'cached-person-uuid',
                    'name' => [
                        'firstName' => 'Cached',
                        'lastName' => 'Person'
                    ]
                ]
            ]
        ];

        // Create mock for cache that indicates cache hit
        $cacheFrontendMock = $this->createMock(FrontendInterface::class);
        $cacheFrontendMock->method('has')->willReturn(true);
        $cacheFrontendMock->method('get')->willReturn($cachedData);

        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->willReturn($cacheFrontendMock);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);

        // WebService should not be called if cache is used
        $webServiceMock = $this->createMock(WebService::class);
        $webServiceMock->expects($this->never())->method('getJson');
        GeneralUtility::addInstance(WebService::class, $webServiceMock);

        // Create the class under test
        $subject = new ClassificationScheme();

        // Prepare config array to be filled
        $config = ['items' => []];

        // Call the method
        $subject->getPersons($config);

        // Assert the result contains cached data
        $this->assertCount(1, $config['items']);
        $this->assertEquals('Person, Cached', $config['items'][0][0]);
        $this->assertEquals('cached-person-uuid', $config['items'][0][1]);
    }

    #[Test]
    public function getProjectsUsesCache(): void
    {
        // Create cache data
        $cachedData = [
            'items' => [
                [
                    'uuid' => 'cached-project-uuid',
                    'acronym' => 'CACHE',
                    'title' => [
                        'text' => [
                            [
                                'value' => 'Cached Project'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Create mock for cache that indicates cache hit
        $cacheFrontendMock = $this->createMock(FrontendInterface::class);
        $cacheFrontendMock->method('has')->willReturn(true);
        $cacheFrontendMock->method('get')->willReturn($cachedData);

        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->willReturn($cacheFrontendMock);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);

        // WebService should not be called if cache is used
        $webServiceMock = $this->createMock(WebService::class);
        $webServiceMock->expects($this->never())->method('getJson');
        GeneralUtility::addInstance(WebService::class, $webServiceMock);

        // Create the class under test
        $subject = new ClassificationScheme();

        // Prepare config array to be filled
        $config = ['items' => []];

        // Call the method
        $subject->getProjects($config);

        // Assert the result contains cached data
        $this->assertCount(1, $config['items']);
        $this->assertEquals('CACHE - Cached Project', $config['items'][0][0]);
        $this->assertEquals('cached-project-uuid', $config['items'][0][1]);
    }

    #[Test]
    public function getTypesFromPublicationsUsesCache(): void
    {
        // Create cache data
        $cachedData = [
            'items' => [
                [
                    'containedClassifications' => [
                        // Your test data here
                    ]
                ]
            ]
        ];

        // Create mock for cache that indicates cache hit
        $cacheFrontendMock = $this->createMock(FrontendInterface::class);
        $cacheFrontendMock->method('has')->willReturn(true);
        $cacheFrontendMock->method('get')->willReturn($cachedData);

        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->willReturn($cacheFrontendMock);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);

        // WebService should not be called if cache is used
        $webServiceMock = $this->createMock(WebService::class);
        $webServiceMock->expects($this->never())->method('getJson');
        GeneralUtility::addInstance(WebService::class, $webServiceMock);

        // Create a partial mock to test the sorted2items method
        $subject = $this->getMockBuilder(ClassificationScheme::class)
            ->onlyMethods(['sortClassification', 'isValidPublicationTypesData'])
            ->getMock();

        $subject->method('isValidPublicationTypesData')->willReturn(true);
        $subject->method('sortClassification')->willReturn([
            [
                'title' => 'Cached Category',
                'uri' => '/test/cached',
                'child' => [
                    [
                        'title' => 'Cached Item',
                        'uri' => '/test/cached-child'
                    ]
                ]
            ]
        ]);

        // Prepare config array to be filled
        $config = ['items' => []];

        // Call the method
        $subject->getTypesFromPublications($config);

        // Assert the result
        $this->assertCount(2, $config['items']);
        $this->assertEquals('----- Cached Category: -----', $config['items'][0][0]);
        $this->assertEquals('--div--', $config['items'][0][1]);
        $this->assertEquals('Cached Item', $config['items'][1][0]);
        $this->assertEquals('/test/cached-child', $config['items'][1][1]);
    }

    #[Test]
    public function classificationHasChildReturnsTrueWhenValidChildExists(): void
    {
        // Create a reflection method to access the private method
        $reflectionClass = new \ReflectionClass(ClassificationScheme::class);
        $method = $reflectionClass->getMethod('classificationHasChild');
        $method->setAccessible(true);

        $subject = new ClassificationScheme();

        $parent = [
            'classificationRelations' => [
                [
                    'relationType' => [
                        'uri' => '/dk/atira/pure/core/hierarchies/child'
                    ],
                    'relatedTo' => [
                        'term' => [
                            'text' => [
                                [
                                    'value' => 'Valid Child'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $method->invoke($subject, $parent);

        $this->assertTrue($result);
    }

    #[Test]
    public function classificationHasChildReturnsFalseWhenNoValidChildExists(): void
    {
        // Create a reflection method to access the private method
        $reflectionClass = new \ReflectionClass(ClassificationScheme::class);
        $method = $reflectionClass->getMethod('classificationHasChild');
        $method->setAccessible(true);

        $subject = new ClassificationScheme();

        $parent = [
            'classificationRelations' => [
                [
                    'relationType' => [
                        'uri' => '/dk/atira/pure/core/hierarchies/child'
                    ],
                    'relatedTo' => [
                        'term' => [
                            'text' => [
                                [
                                    'value' => '<placeholder>'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $method->invoke($subject, $parent);

        $this->assertFalse($result);
    }

    #[Test]
    public function classificationHasChildReturnsFalseWhenNoRelationsExist(): void
    {
        // Create a reflection method to access the private method
        $reflectionClass = new \ReflectionClass(ClassificationScheme::class);
        $method = $reflectionClass->getMethod('classificationHasChild');
        $method->setAccessible(true);

        $subject = new ClassificationScheme();

        $parent = [
            // No classificationRelations key
        ];

        $result = $method->invoke($subject, $parent);

        $this->assertFalse($result);
    }

    #[Test]
    public function isChildEnabledOnRootLevelReturnsTrueWhenChildIsDisabled(): void
    {
        // Create a reflection method to access the private method
        $reflectionClass = new \ReflectionClass(ClassificationScheme::class);
        $method = $reflectionClass->getMethod('isChildEnabledOnRootLevel');
        $method->setAccessible(true);

        $subject = new ClassificationScheme();

        $roots = [
            'items' => [
                [
                    'containedClassifications' => [
                        [
                            'uri' => '/test/child1',
                            'disabled' => true
                        ]
                    ]
                ]
            ]
        ];

        $result = $method->invoke($subject, $roots, '/test/child1');

        $this->assertTrue($result);
    }

    #[Test]
    public function isChildEnabledOnRootLevelReturnsFalseWhenChildIsEnabled(): void
    {
        // Create a reflection method to access the private method
        $reflectionClass = new \ReflectionClass(ClassificationScheme::class);
        $method = $reflectionClass->getMethod('isChildEnabledOnRootLevel');
        $method->setAccessible(true);

        $subject = new ClassificationScheme();

        $roots = [
            'items' => [
                [
                    'containedClassifications' => [
                        [
                            'uri' => '/test/child1',
                            'disabled' => false
                        ]
                    ]
                ]
            ]
        ];

        $result = $method->invoke($subject, $roots, '/test/child1');

        $this->assertFalse($result);
    }

    #[Test]
    public function isChildEnabledOnRootLevelReturnsFalseWhenChildNotFound(): void
    {
        // Create a reflection method to access the private method
        $reflectionClass = new \ReflectionClass(ClassificationScheme::class);
        $method = $reflectionClass->getMethod('isChildEnabledOnRootLevel');
        $method->setAccessible(true);

        $subject = new ClassificationScheme();

        $roots = [
            'items' => [
                [
                    'containedClassifications' => [
                        [
                            'uri' => '/test/child1',
                            'disabled' => true
                        ]
                    ]
                ]
            ]
        ];

        $result = $method->invoke($subject, $roots, '/test/child2');

        $this->assertFalse($result);
    }
}

