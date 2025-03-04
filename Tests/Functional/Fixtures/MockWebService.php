<?php

namespace Univie\UniviePure\Tests\Functional\Fixtures;

use Univie\UniviePure\Service\WebService;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * A testable WebService subclass that overrides key methods for testing
 */
class MockWebService extends WebService
{
    /**
     * @var array Data to return for getJson calls
     */
    protected array $jsonData = [];

    /**
     * @var array Data to return for getSingleResponse calls
     */
    protected array $singleResponseData = [];

    /**
     * @var array Data to return for getAlternativeSingleResponse calls
     */
    protected array $alternativeSingleResponseData = [];

    /**
     * Constructor with properly initialized dependencies
     */
    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        FrontendInterface $cache,
        FlashMessageService $flashMessageService,
        LoggerInterface $logger,
        ExtensionConfiguration $extensionConfiguration
    ) {
        parent::__construct(
            $client,
            $requestFactory,
            $streamFactory,
            $cache,
            $flashMessageService,
            $logger,
            $extensionConfiguration
        );

        // Initialize configuration with test values
        $this->initializeConfiguration();
    }

    /**
     * Set mock data for getJson
     *
     * @param string $endpoint The endpoint
     * @param array $data The data to return
     */
    public function setJsonData(string $endpoint, array $data): void
    {
        $this->jsonData[$endpoint] = $data;
    }

    /**
     * Set mock data for getSingleResponse
     *
     * @param string $endpoint The endpoint
     * @param string $uuid The UUID
     * @param array $data The data to return
     */
    public function setSingleResponseData(string $endpoint, string $uuid, array $data): void
    {
        $key = $endpoint . '/' . $uuid;
        $this->singleResponseData[$key] = $data;
    }

    /**
     * Set mock data for getAlternativeSingleResponse
     *
     * @param string $endpoint The endpoint
     * @param string $uuid The UUID
     * @param array $data The data to return
     */
    public function setAlternativeSingleResponseData(string $endpoint, string $uuid, array $data): void
    {
        $key = $endpoint . '/' . $uuid;
        $this->alternativeSingleResponseData[$key] = $data;
    }

    /**
     * Override to return mock data
     *
     * @param string $endpoint API endpoint
     * @param string $data XML data to send
     * @param string $language Language code
     * @return array|null The API response
     */
    public function getJson(string $endpoint, string $data, string $language = 'de_DE'): ?array
    {
        // Return mock data if available for this endpoint
        if (isset($this->jsonData[$endpoint])) {
            return $this->jsonData[$endpoint];
        }

        // Return a default response for testing
        return [
            'count' => 0,
            'offset' => 0,
            'items' => []
        ];
    }

    /**
     * Override to return mock data
     *
     * @param string $endpoint API endpoint
     * @param string $uuid UUID
     * @param string $responseType Response format
     * @param bool $decoded Whether to decode the response
     * @param string $renderer Rendering type
     * @param string|null $lang Language code
     * @return \SimpleXMLElement|array|string|null The API response
     */
    public function getSingleResponse(
        string $endpoint,
        string $uuid,
        string $responseType = 'json',
        bool $decoded = true,
        ?string $renderer = null,
        ?string $lang = null
    ): \SimpleXMLElement|array|string|null {
        $key = $endpoint . '/' . $uuid;

        // Return mock data if available for this key
        if (isset($this->singleResponseData[$key])) {
            return $this->singleResponseData[$key];
        }

        // Return a default response for testing
        return [
            'items' => [
                [
                    'uuid' => $uuid,
                    'type' => 'unknown'
                ]
            ]
        ];
    }

    /**
     * Override to return mock data
     *
     * @param string $endpoint API endpoint
     * @param string $uuid UUID
     * @param string $format Response format
     * @param string $language Language code
     * @return array|null The API response
     */
    public function getAlternativeSingleResponse(
        string $endpoint,
        string $uuid,
        string $format = 'json',
        string $language = 'de_DE'
    ): ?array {
        $key = $endpoint . '/' . $uuid;

        // Return mock data if available for this key
        if (isset($this->alternativeSingleResponseData[$key])) {
            return $this->alternativeSingleResponseData[$key];
        }

        // Return a default response for testing
        return [
            'items' => [
                [
                    'uuid' => $uuid,
                    'info' => [
                        'portalUrl' => 'https://example.com/portal/' . $endpoint . '/' . $uuid
                    ]
                ]
            ]
        ];
    }

    /**
     * Override to avoid loading .env file
     */
    protected function initializeConfiguration(): void
    {
        // Set test values directly
        $this->setConfig('server', 'https://example.com');
        $this->setConfig('apiKey', 'test-api-key');
        $this->setConfig('versionPath', '/api/v1');
    }
}
