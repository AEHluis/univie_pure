<?php
namespace Univie\UniviePure\Tests\Unit\Endpoints;

class FakeWebServiceClassification extends \Univie\UniviePure\Service\WebService
{

    public $server;
    public $apiKey;
    public $versionPath;

    public function __construct()
    {
        // Bypass parent's __construct()
        $this->server = 'http://fake';
        $this->apiKey = 'fakeapikey';
        $this->versionPath = '/fake/';
    }

    /**
     * Return a fake JSON response for organisational-units queries.
     */
    public function getJson(string $endpoint, string $xml): ?array
    {
        if ($endpoint === 'organisational-units') {
            return [
                'count' => 2,
                'items' => [
                    [
                        'name' => [
                            'text' => [
                                0 => ['value' => 'Fake Organisation 1']
                            ]
                        ],
                        'uuid' => 'org1'
                    ],
                    [
                        'name' => [
                            'text' => [
                                0 => ['value' => 'Fake Organisation 2']
                            ]
                        ],
                        'uuid' => 'org2'
                    ]
                ]
            ];
        }
        if ($endpoint === 'persons') {
            return [
                'count' => 2,
                'items' => [
                    [
                        'name' => ['lastName' => 'Doe', 'firstName' => 'John'],
                        'uuid' => 'p1'
                    ],
                    [
                        'name' => ['lastName' => 'Smith', 'firstName' => 'Jane'],
                        'uuid' => 'p2'
                    ]
                ]
            ];
        }
        return [];
    }
}