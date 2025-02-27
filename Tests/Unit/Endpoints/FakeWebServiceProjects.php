<?php
namespace Univie\UniviePure\Tests\Unit\Endpoints;

class FakeWebServiceProjects extends \Univie\UniviePure\Service\WebService
{

    public $server;
    public $apiKey;
    public $versionPath;

    public function __construct()
    {
        // Do not call the parent's __construct(); manually set required properties.
        $this->server = 'http://fake';
        $this->apiKey = 'fakeapikey';
        $this->versionPath = '/fake/';
    }

    /**
     * Fake response for a single project query.
     *
     * @param string $endpoint
     * @param string $q
     * @param string $responseType
     * @param string $lang
     * @return array
     */
    public function getAlternativeSingleResponse($endpoint, $q, $responseType = "json", $lang = "de_DE"): array|\SimpleXMLElement|null
    {
        if ($endpoint === 'projects') {
            return [
                'code' => '200',
                'data' => 'singleProject:' . $q . ':' . $lang,
            ];
        }
        return [];
    }

    /**
     * Fake XML response for a projects list query.
     *
     * Returns a fixed array structure representing a response with two projects.
     *
     * @param string $endpoint
     * @param string $xml
     * @return array
     */
    public function getXml(string $endpoint, string $xml): ?array
    {
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
                            "descriptions" => [
                                "description" => [
                                    "value" => ["text" => "Description 1"]
                                ]
                            ],
                            "info" => ["portalUrl" => "http://example.com/portal1"]
                        ],
                        [
                            "@attributes" => ["uuid" => "proj2"],
                            "renderings" => [
                                "rendering" => "Project 2 Title"
                            ],
                            "links" => ["link" => "http://example.com/proj2"],
                            "descriptions" => [
                                "description" => [
                                    "value" => ["text" => "Description 2"]
                                ]
                            ],
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