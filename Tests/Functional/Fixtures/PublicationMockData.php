<?php

namespace Univie\UniviePure\Tests\Functional\Fixtures;

/**
 * Provides mock data for publications
 */
class PublicationMockData
{
    /**
     * Get mock JSON response for publication list
     *
     * @return array
     */
    public static function getPublicationListData(): array
    {
        return [
            'count' => 2,
            'offset' => 0,
            'items' => [
                [
                    'uuid' => '12345678-1234-1234-1234-123456789abc',
                    'renderings' => [
                        [
                            'html' => '<div class="publication">Publication 1 Title</div>'
                        ]
                    ],
                    'publicationStatuses' => [
                        [
                            'current' => 'true',
                            'publicationStatus' => [
                                'uri' => '/dk/atira/pure/researchoutput/status/published',
                                'term' => [
                                    'text' => [
                                        [
                                            'value' => 'Published'
                                        ]
                                    ]
                                ]
                            ],
                            'publicationDate' => [
                                'year' => '2023'
                            ]
                        ]
                    ]
                ],
                [
                    'uuid' => '87654321-4321-4321-4321-cba987654321',
                    'renderings' => [
                        [
                            'html' => '<div class="publication">Publication 2 Title</div>'
                        ]
                    ],
                    'publicationStatuses' => [
                        [
                            'current' => 'true',
                            'publicationStatus' => [
                                'uri' => '/dk/atira/pure/researchoutput/status/published',
                                'term' => [
                                    'text' => [
                                        [
                                            'value' => 'Published'
                                        ]
                                    ]
                                ]
                            ],
                            'publicationDate' => [
                                'year' => '2022'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get mock data for a single publication
     *
     * @param string $uuid Publication UUID
     * @return array
     */
    public static function getSinglePublicationData(string $uuid): array
    {
        return [
            'items' => [
                [
                    'uuid' => $uuid,
                    'title' => [
                        'value' => 'Detailed Publication Title'
                    ],
                    'info' => [
                        'portalUrl' => 'http://example.com/publications/' . $uuid
                    ],
                    'abstract' => [
                        'text' => 'This is a detailed abstract of the publication.'
                    ],
                    'publicationStatuses' => [
                        [
                            'current' => 'true',
                            'publicationStatus' => [
                                'uri' => '/dk/atira/pure/researchoutput/status/published',
                                'term' => [
                                    'text' => [
                                        [
                                            'value' => 'Published'
                                        ]
                                    ]
                                ]
                            ],
                            'publicationDate' => [
                                'year' => '2023',
                                'month' => '6',
                                'day' => '15'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
