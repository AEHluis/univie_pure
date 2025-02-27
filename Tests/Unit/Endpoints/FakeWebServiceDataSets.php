<?php
namespace Univie\UniviePure\Tests\Unit\Endpoints;

use Univie\UniviePure\Service\WebService;

/**
 * A Fake WebService for testing DataSets.
 */
class FakeWebServiceDataSets extends WebService
{
    // Declare them so they’re not created dynamically
    protected string $server;
    protected string $apiKey;
    protected string $versionPath;

    public function __construct()
    {
        // We skip calling parent::__construct()
        $this->server      = 'http://fake';
        $this->apiKey      = 'fakeapikey';
        $this->versionPath = '/fake/';
    }
    /**
     * Fake single dataset response for getSingleDataSet().
     *
     * Example:
     *   $endpoint = 'datasets'
     *   $q = $uuid
     */
    public function getAlternativeSingleResponse($endpoint, $q, $responseType = "json", $lang = "de_DE"): array|\SimpleXMLElement|null
    {
        if ($endpoint === 'datasets') {
            return [
                'code' => '200',
                'data' => 'singleDataSet:' . $q . ':' . $lang,
            ];
        }
        return [];
    }

    /**
     * Fake the XML response for a DataSets list query.
     *
     * We return a structure with two "dataSet" items, each having a
     * "renderings" key that is a single string. This way, your DataSets
     * code will convert that string into an array with [0 => ['html'=>'...']].
     *
     * @param string $endpoint
     * @param string $xml
     * @return array|null
     */
    public function getXml(string $endpoint, string $xml): ?array
    {
        if ($endpoint === 'datasets') {
            // Return a structure that your DataSets code can transform
            // into the final shape your test expects:
            //   items => dataSet => [
            //     [
            //       @attributes => [ uuid => 'uuid1' ],
            //       renderings => [ rendering => '...' ],
            //       links => [ link => 'link1' ],
            //       descriptions => [ description => [ value => [ text => '...' ] ] ]
            //     ],
            //     [ second item ],
            //   ],
            //   count => 2
            return [
                "count" => 2,
                "items" => [
                    "dataSet" => [
                        [
                            "@attributes" => ["uuid" => "uuid1"],
                            "renderings" => [
                                // A single string we want to transform
                                "rendering" => "<h2 class=\"title\">Title 1</h2><p class=\"type\">Some type</p><br />"
                            ],
                            "links" => ["link" => "link1"],
                            "descriptions" => [
                                "description" => [
                                    "value" => ["text" => "Description 1"]
                                ]
                            ]
                        ],
                        [
                            "@attributes" => ["uuid" => "uuid2"],
                            "renderings" => [
                                "rendering" => "Simple Title 2"
                            ],
                            "links" => ["link" => "link2"],
                            "descriptions" => [
                                "description" => [
                                    "value" => ["text" => "Description 2"]
                                ]
                            ]
                        ]
                    ]
                ],
                "offset" => 0
            ];
        }

        // Return an empty structure for endpoints we don't handle
        return [];
    }
}