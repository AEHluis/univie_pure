<?php
namespace Univie\UniviePure\Tests\Unit\Endpoints;

class FakeWebServiceResearchOutput extends \Univie\UniviePure\Service\WebService
{

    public $server;
    public $apiKey;
    public $versionPath;


    public function __construct()
    {
        // Do not call parent's __construct(); set required properties manually.
        $this->server = 'http://fake';
        $this->apiKey = 'fakeapikey';
        $this->versionPath = '/fake/';
    }

    /**
     * Fake response for a single publication query.
     *
     * @param string $endpoint
     * @param string $q
     * @param string $responseType
     * @param string $lang
     * @return array
     */
    public function getAlternativeSingleResponse($endpoint, $q, $responseType = "json", $lang = "de_DE"): array|\SimpleXMLElement|null
    {
        if ($endpoint === 'research-outputs') {
            return [
                'code' => '200',
                'data' => 'singlePublication:' . $q . ':' . $lang,
            ];
        }
        return [];
    }

    /**
     * Fake getSingleResponse() that returns a string based on the renderer.
     *
     * @param string $endpoint
     * @param string $uuid
     * @param string $responseType
     * @param bool   $decoded
     * @param string $renderer
     * @param string $lang
     * @return string
     */
    public function getSingleResponse(
        string $endpoint,
        string $uuid,
        string $responseType = 'xml',
        bool $decoded = true,
        string $renderer = 'standard',
        ?string $lang = 'de_DE'
    ): array|string|\SimpleXMLElement|null {
        if ($endpoint === 'research-outputs') {
            return 'fake ' . $renderer . ' rendering for ' . $uuid . ' in ' . $lang;
        }
        return '';
    }

    /**
     * Fake JSON response for a research-outputs list query.
     *
     * Returns a fixed structure with two publications.
     *
     * The structure contains a "contributionToJournal" key whose value is an indexed array.
     *
     * @param string $endpoint
     * @param string $xml
     * @return array
     */
    public function getJson(string $endpoint, string $xml): ?array
    {
        if ($endpoint === 'research-outputs') {
            return [
                'count' => 2,
                'contributionToJournal' => [
                    0 => [
                        'uuid' => 'pub1',
                        'rendering' =>  'Fake Publication Title',

                        'publicationStatuses' => [
                            'publicationDate' => ['year' => '2020'],
                            'publicationStatus' => [
                                'uri' => '/dk/atira/pure/researchoutput/status/published',
                                'term' => [
                                    'text' => [
                                        ['value' => 'Published']
                                    ]
                                ]
                            ]
                        ],
                        'personAssociations' => []
                    ],
                    1 => [
                        'uuid' => 'pub2',
                        'rendering' => 'Fake Publication Title 2',
                        'publicationStatuses' => [
                            'publicationDate' => ['year' => '2021'],
                            'publicationStatus' => [
                                'uri' => '/dk/atira/pure/researchoutput/status/inpress',
                                'term' => [
                                    'text' => [
                                        ['value' => 'In Press']
                                    ]
                                ]
                            ]
                        ],
                        'personAssociations' => []
                    ]
                ]
            ];
        }
        return [];
    }
}