<?php

namespace Univie\UniviePure\Tests\Functional\Fixtures;

/**
 * Provides mock data for projects
 */
class ProjectMockData
{
    /**
     * Get mock JSON response for project list
     *
     * @return array
     */
    public static function getProjectListData(): array
    {
        return [
            'offset' => 0,
            'count' => 2,
            'items' => [
                'project' => [
                    [
                        '@attributes' => [
                            'uuid' => 'proj-12345678-1234-1234-1234-123456789abc'
                        ],
                        'renderings' => [
                            'rendering' => '<div class="project">Project 1 Title</div>'
                        ],
                        'links' => [
                            'link' => 'http://example.com/projects/1'
                        ],
                        'descriptions' => [
                            'description' => [
                                'value' => [
                                    'text' => 'This is the description of Project 1.'
                                ]
                            ]
                        ],
                        'info' => [
                            'portalUrl' => 'http://example.com/portal/projects/1'
                        ]
                    ],
                    [
                        '@attributes' => [
                            'uuid' => 'proj-87654321-4321-4321-4321-cba987654321'
                        ],
                        'renderings' => [
                            'rendering' => '<div class="project">Project 2 Title</div>'
                        ],
                        'links' => [
                            'link' => 'http://example.com/projects/2'
                        ],
                        'descriptions' => [
                            'description' => [
                                'value' => [
                                    'text' => 'This is the description of Project 2.'
                                ]
                            ]
                        ],
                        'info' => [
                            'portalUrl' => 'http://example.com/portal/projects/2'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get mock data for a single project
     *
     * @param string $uuid Project UUID
     * @return array
     */
    public static function getSingleProjectData(string $uuid): array
    {
        return [
            'items' => [
                [
                    'uuid' => $uuid,
                    'title' => [
                        'value' => 'Detailed Project Title'
                    ],
                    'info' => [
                        'portalUrl' => 'http://example.com/projects/' . $uuid
                    ],
                    'description' => [
                        'text' => 'This is a detailed description of the project.'
                    ],
                    'startDate' => '2023-01-01',
                    'endDate' => '2025-12-31',
                    'status' => 'RUNNING'
                ]
            ]
        ];
    }
}
