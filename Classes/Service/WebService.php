<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
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
use Univie\UniviePure\Utility\CommonUtilities;
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
        private readonly ClientInterface         $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface  $streamFactory,
        private readonly FrontendInterface       $cache,
        private readonly FlashMessageService     $flashMessageService,
        private readonly LoggerInterface         $logger,
        private readonly ExtensionConfiguration  $extensionConfiguration
    )
    {
        $this->initializeConfiguration();
    }

    private string $server = '';
    private string $proxy = '';
    private string $apiKey = '';
    private string $versionPath = '';

    protected function initializeConfiguration(): void
    {
        try {
            $dotEnv = new DotEnv(Environment::getProjectPath() . "/.env");
            $dotEnv->load();
            $this->setConfig('server', $dotEnv->variables["PURE_URI"]);
            $this->setConfig('apiKey', $dotEnv->variables["PURE_APIKEY"]);
            $this->setConfig('versionPath', $dotEnv->variables["PURE_ENDPOINT"]);
        } catch (ExtensionConfigurationExtensionNotConfiguredException $e) {
            $this->logger->error('Extension configuration not found', ['exception' => $e]);
        }
    }


    protected function setConfig(string $key, ?string $value): void
    {
        $this->$key = strval($value);
    }


    private function fetchApiResponse(string $endpoint, array $params, string $responseType, bool $decoded = true): array|string|\SimpleXMLElement|null
    {
        if (CommonUtilities::getArrayValue($params,'rendering','') == 'BIBTEX'){
            $locale = $params['locale']; // Store locale value
            unset($params['locale']);    // Remove from params array

        }
        // Build the URI with query parameters from $params
        $uri = (new Uri($this->server . $this->versionPath . $endpoint))->withQuery(http_build_query($params));

        // Add locale as a separate parameter if it was removed
        if (isset($locale)) {
            $uri = $uri->withQuery($uri->getQuery() . '&locale=' . $locale);
        }

        // Extract rendering style for cache identifier (critical for citation styles)
        $renderer = $params['rendering'] ?? 'html';
        $lang = isset($locale) ? $locale : ($params['locale'] ?? null);

        // Update cache identifier to include rendering style and locale
        // This ensures different citation styles (APA, Harvard, etc.) get separate cache entries
        $cacheIdentifier = $this->generateCacheIdentifier(
            $endpoint,
            json_encode(isset($locale) ? array_merge($params, ['locale' => $locale]) : $params),
            $responseType,
            $lang,
            $renderer
        );

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
            $this->addFlashMessage('API Error', $e->getMessage(), ContextualFeedbackSeverity::ERROR);
            return null;
        }
    }


    public function getAlternativeSingleResponse(
        string $endpoint,
        string $q,
        string $responseType = "json",
        string $lang = "de_DE"
    ): array|\SimpleXMLElement|null
    {
        return $this->fetchApiResponse($endpoint, ['q' => $q, 'locale' => $lang], $responseType);
    }


    public function getSingleResponse(
        string  $endpoint,
        string  $uuid,
        string  $responseType = 'json',
        bool    $decoded = true,
        ?string  $renderer = null,
        ?string $lang = null
    ): array|string|\SimpleXMLElement|null
    {
        $params = [];

        // Handle renderer parameter correctly based on type
        if ($renderer === 'bibtex') {
            $params['rendering'] = 'BIBTEX';
        } elseif ($renderer === 'detailsPortal') {
            $params['rendering'] = 'detailsPortal';
        } elseif ($renderer === 'standard') {
            $params['rendering'] = 'standard';
        } elseif ($renderer != null) {
            $params['rendering'] = strtoupper($renderer);
        }

        // Add language parameter if provided
        if ($lang) {
            $params['locale'] = $lang;
        }

        return $this->fetchApiResponse($endpoint . '/' . $uuid, $params, $responseType, $decoded);
    }


    private function checkReturnCodeErrorMsg(?array $result): void
    {
        if ($result && isset($result['data']) && $result['data'] === '500') {
            $this->logAndNotify('Server Error', 'The server returned an error response.', $result);
        }
    }

    private function logAndNotify(string $title, string $message, array $logContext = []): void
    {
        $this->addFlashMessage($title, $message, ContextualFeedbackSeverity::ERROR);
        $this->logger->error($message, $logContext);
    }

    private function getCachedContent(string $cacheIdentifier): ?string
    {
        $result = $this->cache->get($cacheIdentifier);
        return $result === false ? null : $result;
    }

    private function processResponse(string $content, string $responseType, bool $decoded): array|string|null
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
                $xml = simplexml_load_string($content, null, LIBXML_NOCDATA);
                return json_decode(json_encode((array)$xml), true);
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
        return $this->processResponse($response ?? '', 'json', true);
    }

    public function getXml(string $endpoint, string $data): array|\SimpleXMLElement|null
    {
        $response = $this->executeRequest($endpoint, $data, 'xml');
        return $this->processResponse($response ?? '', 'xml', true);
    }

    private function executeRequest(string $endpoint, string $data, string $responseType): ?string
    {
        $cacheIdentifier = sha1($endpoint . $data . $responseType);

        if ($cachedResponse = $this->getCachedContent($cacheIdentifier)) {
            return $cachedResponse;
        }

        $response = $this->performRequest(new Uri($this->server . $this->versionPath . $endpoint), $data, $responseType);
        if ($response && strlen($response) > self::MINIMUM_RESPONSE_SIZE) {
            $this->cache->set($cacheIdentifier, $response, [], self::CACHE_LIFETIME);
        }

        return $response;
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
            $responseBody = (string)$response->getBody();

            if ($statusCode < 200 || $statusCode >= 300) {
                // Log error if non-2xx with detailed request/response info
                $this->logger->error(
                    sprintf(
                        'Request to %s returned a non-2xx status code: %d',
                        (string)$uri,
                        $statusCode
                    ),
                    [
                        'status_code' => $statusCode,
                        'request_xml' => $data,
                        'response_body' => $responseBody,
                        'response_headers' => $response->getHeaders(),
                    ]
                );

                return null;
            }
            return $responseBody;
        } catch (\Exception $e) {
            $this->logger->error('Request failed', [
                'exception' => $e,
                'uri' => (string)$uri,
                'request_xml' => $data,
            ]);
            return null;
        }
    }

    public function generateCacheIdentifier(string $endpoint, string $uuid, ?string $lang = null, string $responseType = 'json', string $renderer = 'html'): string
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

    private function addFlashMessage(string $title, string $message, ContextualFeedbackSeverity $severity): void
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
