<?php

declare(strict_types=1);

namespace Univie\UniviePure\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Univie\UniviePure\Service\CslDataTransformer;

/**
 * Test case for CslDataTransformer
 */
class CslDataTransformerTest extends TestCase
{
    private CslDataTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new CslDataTransformer();
    }

    #[Test]
    public function transformResearchOutputCreatesValidCslJson(): void
    {
        $pureData = [
            'uuid' => 'test-uuid-123',
            'type' => 'contributionToJournal',
            'title' => 'Test Publication Title',
            'publicationYear' => 2024,
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertEquals('test-uuid-123', $result['id']);
        $this->assertEquals('article-journal', $result['type']);
        $this->assertEquals('Test Publication Title', $result['title']);
    }

    #[Test]
    public function transformResearchOutputMapsTypes(): void
    {
        $testCases = [
            ['type' => 'contributionToJournal', 'expected' => 'article-journal'],
            ['type' => 'contributionToBookAnthology', 'expected' => 'chapter'],
            ['type' => 'book', 'expected' => 'book'],
            ['type' => 'contributionToConference', 'expected' => 'paper-conference'],
            ['type' => 'thesis', 'expected' => 'thesis'],
            ['type' => 'patent', 'expected' => 'patent'],
            ['type' => 'report', 'expected' => 'report'],
        ];

        foreach ($testCases as $testCase) {
            $result = $this->transformer->transformResearchOutput([
                'uuid' => 'test',
                'type' => $testCase['type'],
            ]);

            $this->assertEquals(
                $testCase['expected'],
                $result['type'],
                "Type {$testCase['type']} should map to {$testCase['expected']}"
            );
        }
    }

    #[Test]
    public function transformResearchOutputHandlesContributors(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'contributors' => [
                [
                    'name' => [
                        'firstName' => 'John',
                        'lastName' => 'Doe',
                    ],
                ],
                [
                    'name' => [
                        'firstName' => 'Jane',
                        'lastName' => 'Smith',
                    ],
                ],
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertArrayHasKey('author', $result);
        $this->assertCount(2, $result['author']);
        $this->assertEquals('Doe', $result['author'][0]['family']);
        $this->assertEquals('John', $result['author'][0]['given']);
        $this->assertEquals('Smith', $result['author'][1]['family']);
        $this->assertEquals('Jane', $result['author'][1]['given']);
    }

    #[Test]
    public function transformResearchOutputHandlesPersonAssociations(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'personAssociations' => [
                [
                    'name' => [
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                    ],
                ],
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertArrayHasKey('author', $result);
        $this->assertCount(1, $result['author']);
        $this->assertEquals('Mustermann', $result['author'][0]['family']);
        $this->assertEquals('Max', $result['author'][0]['given']);
    }

    #[Test]
    public function transformResearchOutputHandlesDate(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'publicationYear' => 2023,
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertArrayHasKey('issued', $result);
        $this->assertEquals([[2023]], $result['issued']['date-parts']);
    }

    #[Test]
    public function transformResearchOutputHandlesFullDate(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'publicationDate' => '2023-06-15',
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertArrayHasKey('issued', $result);
        $this->assertEquals([[2023, 6, 15]], $result['issued']['date-parts']);
    }

    #[Test]
    public function transformResearchOutputHandlesJournalInfo(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'journal' => [
                'title' => 'Nature',
                'volume' => '605',
                'issue' => '7909',
                'pages' => '123-128',
                'issn' => '0028-0836',
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('Nature', $result['container-title']);
        $this->assertEquals('605', $result['volume']);
        $this->assertEquals('7909', $result['issue']);
        $this->assertEquals('123-128', $result['page']);
        $this->assertEquals('0028-0836', $result['ISSN']);
    }

    #[Test]
    public function transformResearchOutputHandlesDoi(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'doi' => '10.1234/test.doi',
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertArrayHasKey('DOI', $result);
        $this->assertEquals('10.1234/test.doi', $result['DOI']);
    }

    #[Test]
    public function transformResearchOutputHandlesDoiInElectronicVersions(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'electronicVersions' => [
                [
                    'doi' => '10.5678/electronic.doi',
                ],
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertArrayHasKey('DOI', $result);
        $this->assertEquals('10.5678/electronic.doi', $result['DOI']);
    }

    #[Test]
    public function transformResearchOutputHandlesPublisher(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'publisher' => 'Springer',
            'publisherLocation' => 'Berlin',
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('Springer', $result['publisher']);
        $this->assertEquals('Berlin', $result['publisher-place']);
    }

    #[Test]
    public function transformResearchOutputHandlesAbstract(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'abstract' => [
                'value' => 'This is the abstract of the publication.',
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('This is the abstract of the publication.', $result['abstract']);
    }

    #[Test]
    public function transformResearchOutputHandlesUrl(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'info' => [
                'portalUrl' => 'https://pure.example.com/publication/123',
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('https://pure.example.com/publication/123', $result['URL']);
        $this->assertArrayHasKey('accessed', $result);
    }

    #[Test]
    public function transformBatchTransformsMultipleItems(): void
    {
        $items = [
            ['uuid' => 'uuid-1', 'title' => 'Publication 1'],
            ['uuid' => 'uuid-2', 'title' => 'Publication 2'],
            ['uuid' => 'uuid-3', 'title' => 'Publication 3'],
        ];

        $result = $this->transformer->transformBatch($items);

        $this->assertCount(3, $result);
        $this->assertEquals('uuid-1', $result[0]['id']);
        $this->assertEquals('uuid-2', $result[1]['id']);
        $this->assertEquals('uuid-3', $result[2]['id']);
    }

    #[Test]
    public function transformResearchOutputHandlesKeywords(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'keywords' => [
                ['value' => 'Machine Learning'],
                ['value' => 'Artificial Intelligence'],
                ['value' => 'Deep Learning'],
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertArrayHasKey('keyword', $result);
        $this->assertStringContainsString('Machine Learning', $result['keyword']);
        $this->assertStringContainsString('Artificial Intelligence', $result['keyword']);
    }

    #[Test]
    public function transformResearchOutputInfersTypeFromJournal(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'type' => 'unknown',
            'journal' => [
                'title' => 'Some Journal',
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('article-journal', $result['type']);
    }

    #[Test]
    public function transformResearchOutputInfersTypeFromConference(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'type' => 'unknown',
            'conference' => [
                'name' => 'International Conference on Testing',
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('paper-conference', $result['type']);
    }

    #[Test]
    public function transformResearchOutputHandlesNestedTitleFormat(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'title' => 'Simple Title',
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('Simple Title', $result['title']);
    }

    #[Test]
    public function transformResearchOutputHandlesIsbn(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'isbn' => '978-3-16-148410-0',
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('978-3-16-148410-0', $result['ISBN']);
    }

    #[Test]
    public function transformResearchOutputHandlesEdition(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'edition' => '2nd',
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('2nd', $result['edition']);
    }

    #[Test]
    public function transformResearchOutputGeneratesIdIfMissing(): void
    {
        $pureData = [
            'title' => 'No UUID Publication',
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertArrayHasKey('id', $result);
        $this->assertNotEmpty($result['id']);
    }

    #[Test]
    public function transformResearchOutputHandlesConferenceEvent(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'conference' => [
                'name' => 'ACM SIGCHI Conference 2024',
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('ACM SIGCHI Conference 2024', $result['event']);
    }

    #[Test]
    public function transformResearchOutputHandlesOpenApiRoleObject(): void
    {
        // OpenAPI returns role as object with URI
        $pureData = [
            'uuid' => 'test-uuid',
            'contributors' => [
                [
                    'name' => [
                        'firstName' => 'John',
                        'lastName' => 'Doe',
                    ],
                    'role' => [
                        'uri' => '/dk/atira/pure/researchoutput/roles/contributiontojournal/author',
                        'term' => ['value' => 'Author'],
                    ],
                ],
                [
                    'name' => [
                        'firstName' => 'Jane',
                        'lastName' => 'Editor',
                    ],
                    'role' => [
                        'uri' => '/dk/atira/pure/researchoutput/roles/contributiontojournal/editor',
                        'term' => ['value' => 'Editor'],
                    ],
                ],
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        // Should only include author, not editor
        $this->assertArrayHasKey('author', $result);
        $this->assertCount(1, $result['author']);
        $this->assertEquals('Doe', $result['author'][0]['family']);
    }

    #[Test]
    public function transformResearchOutputHandlesMultilingualTitle(): void
    {
        // OpenAPI returns title as multilingual object
        $pureData = [
            'uuid' => 'test-uuid',
            'title' => [
                'text' => [
                    ['locale' => 'en_GB', 'value' => 'English Title'],
                    ['locale' => 'de_DE', 'value' => 'German Title'],
                ],
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('English Title', $result['title']);
    }

    #[Test]
    public function transformResearchOutputHandlesBooleanCurrentStatus(): void
    {
        // OpenAPI returns boolean true, not string 'true'
        $pureData = [
            'uuid' => 'test-uuid',
            'publicationStatuses' => [
                [
                    'current' => true,  // boolean, not string
                    'publicationDate' => [
                        'year' => 2024,
                    ],
                ],
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertArrayHasKey('issued', $result);
        $this->assertEquals([[2024]], $result['issued']['date-parts']);
    }

    #[Test]
    public function transformResearchOutputHandlesStringCurrentStatus(): void
    {
        // Some Pure payloads return boolean-like strings.
        $pureData = [
            'uuid' => 'test-uuid',
            'publicationStatuses' => [
                [
                    'current' => 'true',  // string, not boolean
                    'publicationDate' => [
                        'year' => 2023,
                    ],
                ],
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertArrayHasKey('issued', $result);
        $this->assertEquals([[2023]], $result['issued']['date-parts']);
    }

    #[Test]
    public function transformResearchOutputExtractsGermanLocale(): void
    {
        // OpenAPI returns multilingual title - request German
        $pureData = [
            'uuid' => 'test-uuid',
            'title' => [
                'text' => [
                    ['locale' => 'en_GB', 'value' => 'English Title'],
                    ['locale' => 'de_DE', 'value' => 'Deutscher Titel'],
                ],
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData, 'de_DE');

        $this->assertEquals('Deutscher Titel', $result['title']);
    }

    #[Test]
    public function transformResearchOutputFallsBackToEnglishWhenLocaleNotFound(): void
    {
        // OpenAPI returns multilingual title without requested locale
        $pureData = [
            'uuid' => 'test-uuid',
            'title' => [
                'text' => [
                    ['locale' => 'en_GB', 'value' => 'English Title'],
                    ['locale' => 'fr_FR', 'value' => 'French Title'],
                ],
            ],
        ];

        // Request German but it's not available - should fall back to English
        $result = $this->transformer->transformResearchOutput($pureData, 'de_DE');

        $this->assertEquals('English Title', $result['title']);
    }

    #[Test]
    public function setPreferredLocaleAffectsExtraction(): void
    {
        $pureData = [
            'uuid' => 'test-uuid',
            'title' => [
                'text' => [
                    ['locale' => 'en_GB', 'value' => 'English Title'],
                    ['locale' => 'de_DE', 'value' => 'Deutscher Titel'],
                ],
            ],
        ];

        // Set German as preferred locale
        $this->transformer->setPreferredLocale('de_DE');
        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('Deutscher Titel', $result['title']);

        // Reset to English
        $this->transformer->setPreferredLocale('en_GB');
        $result = $this->transformer->transformResearchOutput($pureData);

        $this->assertEquals('English Title', $result['title']);
    }

    #[Test]
    public function transformBatchUsesLocale(): void
    {
        $items = [
            [
                'uuid' => 'uuid-1',
                'title' => [
                    'text' => [
                        ['locale' => 'en_GB', 'value' => 'English 1'],
                        ['locale' => 'de_DE', 'value' => 'German 1'],
                    ],
                ],
            ],
            [
                'uuid' => 'uuid-2',
                'title' => [
                    'text' => [
                        ['locale' => 'en_GB', 'value' => 'English 2'],
                        ['locale' => 'de_DE', 'value' => 'German 2'],
                    ],
                ],
            ],
        ];

        $result = $this->transformer->transformBatch($items, 'de_DE');

        $this->assertEquals('German 1', $result[0]['title']);
        $this->assertEquals('German 2', $result[1]['title']);
    }

    #[Test]
    public function transformResearchOutputHandlesOpenApiLocaleMapFormat(): void
    {
        // Pure OpenAPI Swagger format: { "value": { "en_GB": "text", "de_DE": "text" } }
        $pureData = [
            'uuid' => 'test-uuid',
            'title' => [
                'value' => [
                    'en_GB' => 'English Title from OpenAPI',
                    'de_DE' => 'Deutscher Titel aus OpenAPI',
                ],
            ],
        ];

        // Default should be English
        $result = $this->transformer->transformResearchOutput($pureData);
        $this->assertEquals('English Title from OpenAPI', $result['title']);

        // Explicitly request German
        $result = $this->transformer->transformResearchOutput($pureData, 'de_DE');
        $this->assertEquals('Deutscher Titel aus OpenAPI', $result['title']);
    }

    #[Test]
    public function transformResearchOutputHandlesDirectLocaleMap(): void
    {
        // Direct locale map format: { "en_GB": "text", "de_DE": "text" }
        $pureData = [
            'uuid' => 'test-uuid',
            'title' => [
                'en_GB' => 'Direct English Title',
                'de_DE' => 'Direkter deutscher Titel',
            ],
        ];

        $result = $this->transformer->transformResearchOutput($pureData, 'de_DE');
        $this->assertEquals('Direkter deutscher Titel', $result['title']);
    }

    #[Test]
    public function transformResearchOutputLocaleMapFallsBackToEnglish(): void
    {
        // OpenAPI format with only English and French
        $pureData = [
            'uuid' => 'test-uuid',
            'title' => [
                'value' => [
                    'en_GB' => 'English Only',
                    'fr_FR' => 'French Only',
                ],
            ],
        ];

        // Request German but not available - should fall back to English
        $result = $this->transformer->transformResearchOutput($pureData, 'de_DE');
        $this->assertEquals('English Only', $result['title']);
    }

    #[Test]
    public function transformResearchOutputLocaleMapMatchesLanguageFamily(): void
    {
        // OpenAPI format with de_AT instead of de_DE
        $pureData = [
            'uuid' => 'test-uuid',
            'title' => [
                'value' => [
                    'en_GB' => 'English',
                    'de_AT' => 'Austrian German',
                ],
            ],
        ];

        // Request de_DE should match de_AT (same language family)
        $result = $this->transformer->transformResearchOutput($pureData, 'de_DE');
        $this->assertEquals('Austrian German', $result['title']);
    }
}
