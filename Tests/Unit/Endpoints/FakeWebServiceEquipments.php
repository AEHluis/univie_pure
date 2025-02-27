<?php
namespace Univie\UniviePure\Tests\Unit\Endpoints;

class FakeWebServiceEquipments extends \Univie\UniviePure\Service\WebService {

    public $server;
    public $apiKey;
    public $versionPath;


    public function __construct() {
        // Do not call the parent's __construct() to avoid DotEnv/Environment calls.
        $this->server = 'http://fake';
        $this->apiKey = 'fakeapikey';
        $this->versionPath = '/fake/';
    }

    public function getAlternativeSingleResponse($endpoint, $q, $responseType = "json", $lang = "de_DE"): array|\SimpleXMLElement|null {
        if ($endpoint === 'equipments' || $endpoint === 'projects') {
            return [
                'code' => '200',
                'data' => 'single' . ucfirst($endpoint) . ':' . $q . ':' . $lang,
            ];
        }
        return [];
    }

    public function getXml(string $endpoint, string $xml): ?array
    {
        if ($endpoint === 'equipments') {
            return [
                "count" => 2,
                "items" => [
                    "equipment" => [
                        [
                            "@attributes" => ["uuid" => "eq1"],
                            "renderings" => [
                                "rendering" => "<h2 class=\"title\">Equipment 1</h2><p></p>"
                            ],
                            "links" => ["link" => "http://example.com/eq1"],
                            "info" => ["portalUrl" => "http://example.com/eq1"],
                            "contactPersons" => [
                                "contactPerson" => ["name" => ["text" => "John Doe"]]
                            ],
                            "emails" => [
                                "email" => ["value" => "EMAIL@EXAMPLE.COM"]
                            ],
                            "webAddresses" => [
                                "webAddress" => ["value" => ["text" => "http://example.com"]]
                            ],
                        ],
                        [
                            "@attributes" => ["uuid" => "eq2"],
                            "renderings" => [
                                "rendering" => "Equipment 2 Title"
                            ],
                            "links" => ["link" => "http://example.com/eq2"],
                            "info" => ["portalUrl" => "http://example.com/eq2"],
                            "contactPersons" => [
                                "contactPerson" => ["name" => ["text" => "Jane Smith"]]
                            ],
                            "emails" => [
                                "email" => ["value" => "info@example.com"]
                            ],
                            "webAddresses" => [
                                "webAddress" => ["value" => ["text" => "http://example.org"]]
                            ],
                        ],
                    ]
                ],
                "offset" => 0,
            ];
        }
        if ($endpoint === 'projects') {
            return [
                "count" => 2,
                "items" => [
                    "project" => [
                        [
                            "@attributes" => ["uuid" => "proj1"],
                            "renderings" => [
                                "rendering" => "<h2 class=\"title\">Project 1 Title</h2><p></p>"
                            ],
                            "links" => ["link" => "http://example.com/proj1"],
                            "descriptions" => ["description" => ["value" => ["text" => "Description 1"]]],
                            "info" => ["portalUrl" => "http://example.com/portal1"]
                        ],
                        [
                            "@attributes" => ["uuid" => "proj2"],
                            "renderings" => [
                                "rendering" => "Project 2 Title"
                            ],
                            "links" => ["link" => "http://example.com/proj2"],
                            "descriptions" => ["description" => ["value" => ["text" => "Description 2"]]],
                            "info" => ["portalUrl" => "http://example.com/portal2"]
                        ]
                    ]
                ],
                "offset" => 0,
            ];
        }
        return [];
    }
}