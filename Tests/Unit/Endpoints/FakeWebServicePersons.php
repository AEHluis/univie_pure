<?php
namespace Univie\UniviePure\Tests\Unit\Endpoints;

class FakeWebServicePersons extends \Univie\UniviePure\Service\WebService
{

    public $server;
    public $apiKey;
    public $versionPath;

    public function __construct()
    {
        // Do not call parent's __construct(); instead set required properties manually.
        $this->server = 'http://fake';
        $this->apiKey = 'fakeapikey';
        $this->versionPath = '/fake/';
    }

    /**
     * Override getJson() to return fixed fake data for the Persons endpoint.
     *
     * If the XML contains the fields tag for portal URL, return a fake portal URL response;
     * otherwise, return a fake profile response.
     *
     * @param string $endpoint
     * @param string $xml
     * @return array
     */
    public function getJson(string $endpoint, string $xml): ?array
    {
        if ($endpoint === 'persons') {
            if (strpos($xml, '<fields>info.portalUrl</fields>') !== false) {
                // This is a call for portal URL.
                return [
                    'items' => [
                        [
                            'info' => [
                                'portalUrl' => 'http://fake-portal'
                            ]
                        ]
                    ]
                ];
            } else {
                // Otherwise, assume it's a profile request.
                return [
                    'items' => [
                        [
                            'rendering' => [
                                ['value' => 'fakeProfile']
                            ]
                        ]
                    ]
                ];
            }
        }
        return [];
    }
}