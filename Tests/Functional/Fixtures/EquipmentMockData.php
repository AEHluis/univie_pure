<?php

namespace Univie\UniviePure\Tests\Functional\Fixtures;

/**
 * Class that provides mock data for equipment tests
 */
class EquipmentMockData
{
    /**
     * Get mock data for equipment list
     *
     * @return array
     */
    public static function getEquipmentsListData(): array
    {
        return [
            'count' => 2,
            'offset' => 0,
            'items' => [
                [
                    'uuid' => 'equip-12345678-1234-1234-1234-123456789abc',
                    'name' => 'Test Equipment 1',
                    'description' => 'Description for test equipment 1',
                    'manufacturer' => 'Manufacturer A',
                    'model' => 'Model X100',
                    'renderings' => [
                        [
                            'html' => '<div class="equipment">Test Equipment 1 Rendering</div>'
                        ]
                    ],
                    'organizationalUnits' => [
                        ['name' => 'Department of Testing']
                    ]
                ],
                [
                    'uuid' => 'equip-87654321-4321-4321-4321-cba987654321',
                    'name' => 'Test Equipment 2',
                    'description' => 'Description for test equipment 2',
                    'manufacturer' => 'Manufacturer B',
                    'model' => 'Model Y200',
                    'renderings' => [
                        [
                            'html' => '<div class="equipment">Test Equipment 2 Rendering</div>'
                        ]
                    ],
                    'organizationalUnits' => [
                        ['name' => 'Department of Research']
                    ]
                ]
            ]
        ];
    }

    /**
     * Get mock data for a single equipment
     *
     * @param string $uuid The equipment UUID
     * @return array
     */
    public static function getSingleEquipmentData(string $uuid): array
    {
        return [
            'items' => [
                [
                    'uuid' => $uuid,
                    'name' => 'Detailed Equipment',
                    'description' => 'Detailed description for test equipment',
                    'manufacturer' => 'Manufacturer X',
                    'model' => 'Model Z500',
                    'acquisitionDate' => '2022-05-15',
                    'renderings' => [
                        [
                            'html' => '<div class="equipment-detail">Test Equipment Detail Rendering</div>'
                        ]
                    ],
                    'organizationalUnits' => [
                        ['name' => 'Department of Testing']
                    ],
                    'accessRestrictions' => 'By appointment only',
                    'location' => 'Room 123, Building A'
                ]
            ]
        ];
    }
}