<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

use TYPO3\CMS\Core\Messaging\ContextualFeedbackSeverity;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Utility\DotEnv;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class WebService
{
    private const CACHE_LIFETIME = 14400; // 4 hours in seconds
    private const MINIMUM_RESPONSE_SIZE = 350;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly FrontendInterface $cache,
        private readonly FlashMessageService $flashMessageService,
        private readonly LoggerInterface $logger,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {
        $this->initializeConfiguration();
    }

    private string $server = '';
    private string $proxy = '';
    private string $apiKey = '';
    private string $versionPath = '';

    private function initializeConfiguration(): void
    {
        try {

            $dotEnv = new DotEnv(Environment::getPublicPath() . "/.env");
            $dotEnv->load();
            $this->setServer($dotEnv->variables["PURE_URI"]);
            $this->setApiKey($dotEnv->variables["PURE_APIKEY"]);
            $this->setVersionPath($dotEnv->variables["PURE_ENDPOINT"]);
        } catch (ExtensionConfigurationExtensionNotConfiguredException $e) {
            $this->logger->error('Extension configuration not found', ['exception' => $e]);
        }
    }
    private function setServer(?string $server): void
    {
        $this->server = strval($server);
    }


    private function setApiKey(?string $apiKey): void
    {
        $this->apiKey = strval($apiKey);
    }


    private function setVersionPath(?string $versionPath): void
    {
        $this->versionPath = strval($versionPath);
    }

    /**
     * Alternative method to get a single response using query parameter instead of UUID
     *
     * @param string $endpoint API endpoint
     * @param string $q Query parameter
     * @param string $responseType Response format (json or xml)
     * @param string $lang Language locale
     * @return array|\SimpleXMLElement|null The response data
     */
    public function getAlternativeSingleResponse(
        string $endpoint,
        string $q,
        string $responseType = "json",
        string $lang = "de_DE"
    ): array|\SimpleXMLElement|null {
        $uri = new Uri($this->server . $this->versionPath . $endpoint);
        $params = [
            'q' => $q,
            'locale' => $lang
        ];
        $uri = $uri->withQuery(http_build_query($params));

        $cacheIdentifier = $this->generateCacheIdentifier($endpoint, $q, $lang, $responseType);

        if ($cachedContent = $this->getCachedContent($cacheIdentifier)) {
            return $this->processResponse($cachedContent, $responseType, true);
        }

        try {
            $request = $this->requestFactory->createRequest('GET', $uri)
                ->withHeader('api-key', $this->apiKey)
                ->withHeader('Accept', 'application/' . $responseType)
                ->withHeader('Content-Type', 'application/xml')
                ->withHeader('charset', 'utf-8');

            $response = $this->client->sendRequest($request);
            $content = (string)$response->getBody();

            if ($response->getStatusCode() === 200 && strlen($content) > self::MINIMUM_RESPONSE_SIZE) {
                $this->cache->set($cacheIdentifier, $content, [], self::CACHE_LIFETIME);
            }

            if ($responseType === 'json') {
                $result = json_decode($content, true);
                $this->checkReturnCodeErrorMsg($result);
                return $result;
            } else {
                if (strpos($content, "DOCTYPE HTML PUBLIC") === false) {
                    // xml response FIS-server should return valid xml
                    return simplexml_load_string($content, null, LIBXML_NOCDATA);
                } else {
                    // FIS-server has crashed and returns html with error messages...
                    $this->checkReturnCodeErrorMsg(['data' => '500', 'title' => 'FIS-Server response Issue']);
                    return null;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('API request failed', ['exception' => $e, 'uri' => (string)$uri]);
            $this->addFlashMessage(
                'API Error',
                $e->getMessage(),
                ContextualFeedbackSeverity::ERROR
            );
            return null;
        }
    }

    public function getSingleResponse(
        string $endpoint,
        string $uuid,
        string $responseType = 'json',
        bool $decoded = true,
        string $renderer = 'html',
        ?string $lang = null
    ): array|string|\SimpleXMLElement|null {
        $uri = $this->buildUri($endpoint, $uuid, $renderer, $lang);
        $cacheIdentifier = $this->generateCacheIdentifier($endpoint, $uuid, $lang, $responseType, $renderer);

        if ($cachedContent = $this->getCachedContent($cacheIdentifier)) {
            return $this->processResponse($cachedContent, $responseType, $decoded);
        }

        try {
            $request = $this->requestFactory->createRequest('GET', $uri)
                ->withHeader('api-key', $this->apiKey)
                ->withHeader('Accept', 'application/' . $responseType)
                ->withHeader('Content-Type', 'application/xml')
                ->withHeader('charset', 'utf-8');
            $response = $this->client->sendRequest($request);
            $content = (string)$response->getBody();

            if ($response->getStatusCode() === 200 && strlen($content) > self::MINIMUM_RESPONSE_SIZE) {
                $this->cache->set($cacheIdentifier, $content, [], self::CACHE_LIFETIME);
            }

            return $this->processResponse($content, $responseType, $decoded);
        } catch (\Exception $e) {
            $this->logger->error('API request failed', ['exception' => $e, 'uri' => (string)$uri]);
            $this->addFlashMessage(
                'API Error',
                $e->getMessage(),
                ContextualFeedbackSeverity::ERROR
            );
            return null;
        }
    }

    /**
     * Check return code and throw error message if needed
     *
     * @param array|null $result
     * @return void
     */
    private function checkReturnCodeErrorMsg(?array $result): void
    {
        if (!$result) {
            return;
        }

        if (isset($result['data']) && $result['data'] === '500') {
            $title = $result['title'] ?? 'Server Error';
            $this->addFlashMessage(
                $title,
                'The server returned an error response.',
                ContextualFeedbackSeverity::ERROR
            );
            $this->logger->error('Server returned error', ['result' => $result]);
        }
    }

    private function getCachedContent(string $cacheIdentifier): ?string
    {
        $result = $this->cache->get($cacheIdentifier);
        return $result === false ? null : $result;
    }

    private function processResponse(string $content, string $responseType, bool $decoded): array|string|\SimpleXMLElement|null
    {
        if (empty($content)) {
            return null;
        }

        if (!$decoded) {
            return $content;
        }

        if ($responseType === 'json') {
            $result = json_decode($content, true);
            $this->checkReturnCodeErrorMsg($result);
            return $result;
        } else {
            if (strpos($content, "DOCTYPE HTML PUBLIC") === false) {
                // XML response - FIS-server should return valid XML
                return simplexml_load_string($content, null, LIBXML_NOCDATA);
            } else {
                // FIS-server has crashed and returns HTML with error messages
                $this->checkReturnCodeErrorMsg(['data' => '500', 'title' => 'FIS-Server response Issue']);
                return null;
            }
        }
    }


    public function getJson(string $endpoint, string $data): ?array
    {
        $response = $this->executeRequest($endpoint, $data, 'json');
        return $response ? json_decode($response, true) : null;
    }

    public function getXml(string $endpoint, string $data): ?array
    {
        $response = $this->executeRequest($endpoint, $data, 'xml');
        if (!$response) {
            return null;
        }

        $xml = simplexml_load_string($response, null, LIBXML_NOCDATA);
        return json_decode(json_encode((array)$xml), true);
    }

    private function executeRequest(string $endpoint, string $data, string $responseType): string|false|null
    {
        $uri = new Uri($this->server . $this->versionPath . $endpoint);
        $cacheIdentifier = sha1($endpoint . $data . $responseType);

        // Skip cache for search requests
        if (str_contains($data, 'searchString')) {
            return $this->performRequest($uri, $data, $responseType);
        }
        // WIP: ToDo: check if response is ok to store to cache
        //return $this->cache->get($cacheIdentifier) ?? $this->performRequest($uri, $data, $responseType);
        return $this->performRequest($uri, $data, $responseType);
    }

    private function performRequest(Uri $uri, string $data, string $responseType): ?string
    {
        try {
            $request = $this->requestFactory->createRequest('POST', $uri)
                ->withHeader('api-key', $this->apiKey)
                ->withHeader('Accept', 'application/' . $responseType)
                ->withHeader('Content-Type', 'application/xml')
                ->withBody($this->streamFactory->createStream($data));
            $response = $this->client->sendRequest($request);
            // Check if response code is 2xx
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                // Log error if non-2xx
                $this->logger->error(
                    sprintf(
                        'Request to %s returned a non-2xx status code: %d',
                        (string)$uri,
                        $statusCode
                    ),
                    [
                        'status_code' => $statusCode,
                    ]
                );

                return null;
            }
            return (string)$response->getBody();
        } catch (\Exception $e) {
            $this->logger->error('Request failed', ['exception' => $e, 'uri' => (string)$uri]);
            return null;
        }
    }

    private function buildUri(string $endpoint, string $uuid, string $renderer, ?string $lang): Uri
    {
        $uri = new Uri($this->server . $this->versionPath . $endpoint . '/' . $uuid);

        if ($renderer !== 'html') {
            $params = ['rendering' => strtoupper($renderer)];
            if ($lang) {
                $params['locale'] = $lang;
            }
            $uri = $uri->withQuery(http_build_query($params));
        }

        return $uri;
    }

    private function generateCacheIdentifier(string $endpoint, string $uuid, ?string $lang = null, string $responseType = 'json', string $renderer = 'html'): string
    {
        // Convert null values to empty strings to avoid TypeError
        $parts = [
            $endpoint,
            $uuid,
            $lang ?? '',
            $responseType,
            $renderer
        ];

        return sha1(implode('|', array_filter($parts, fn($part) => $part !== null)));
    }

    private function addFlashMessage(string $title, string $message, int $severity): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            htmlspecialchars($message),
            htmlspecialchars($title),
            $severity,
            false
        );

        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->enqueue($flashMessage);
    }
}
