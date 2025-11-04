<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\OpenApi;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * OpenAPI REST client for Elsevier Pure API
 *
 * Handles HTTP communication with the Pure OpenAPI endpoints using modern REST patterns.
 * Supports Bearer token authentication, request/response caching, and comprehensive error handling.
 */
class OpenApiClient
{
    private const DEFAULT_TIMEOUT = 30;
    private const CACHE_LIFETIME = 14400; // 4 hours (same as XML API)
    private const MIN_CACHE_SIZE = 350;
    private const MAX_RETRY_ATTEMPTS = 3;

    private string $baseUrl;
    private ?string $bearerToken = null;
    private array $defaultHeaders = [];

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly OpenApiAuthenticator $authenticator,
        private readonly OpenApiResponseParser $responseParser
    ) {
        $this->initializeConfiguration();
    }

    /**
     * Initialize OpenAPI configuration from environment
     */
    private function initializeConfiguration(): void
    {
        $this->baseUrl = rtrim($_ENV['PURE_OPENAPI_URL'] ?? '', '/');

        // Set default headers
        $this->defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'TYPO3-UniviePure-OpenAPI/1.0',
        ];

        // Initialize authentication
        $this->bearerToken = $this->authenticator->getAccessToken();

        if ($this->bearerToken) {
            $this->defaultHeaders['Authorization'] = 'Bearer ' . $this->bearerToken;
        }
    }

    /**
     * Perform GET request to OpenAPI endpoint
     *
     * @param string $endpoint API endpoint path (e.g., '/persons')
     * @param array $queryParams Query parameters as key-value pairs
     * @param array $additionalHeaders Additional headers to merge with defaults
     * @return array Parsed response data
     * @throws OpenApiException
     */
    public function get(string $endpoint, array $queryParams = [], array $additionalHeaders = []): array
    {
        $url = $this->buildUrl($endpoint, $queryParams);
        $cacheKey = $this->generateCacheKey('GET', $url, $queryParams);

        // Check cache first
        if ($cachedResponse = $this->getCachedResponse($cacheKey)) {
            $this->logger->debug('OpenAPI cache hit', ['endpoint' => $endpoint, 'cache_key' => $cacheKey]);
            return $cachedResponse;
        }

        $headers = array_merge($this->defaultHeaders, $additionalHeaders);

        try {
            $startTime = microtime(true);
            $response = $this->sendRequest('GET', $url, $headers);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $data = $this->responseParser->parse($response);

            // Cache successful response
            $this->cacheResponse($cacheKey, $data);

            $this->logger->info('OpenAPI GET request successful', [
                'endpoint' => $endpoint,
                'response_time_ms' => $responseTime,
                'status_code' => $response->getStatusCode(),
                'cache_hit' => false,
            ]);

            return $data;

        } catch (GuzzleException $e) {
            return $this->handleRequestError($e, 'GET', $endpoint);
        }
    }

    /**
     * Perform POST request to OpenAPI endpoint
     *
     * @param string $endpoint API endpoint path
     * @param array $body Request body data
     * @param array $additionalHeaders Additional headers
     * @return array Parsed response data
     * @throws OpenApiException
     */
    public function post(string $endpoint, array $body = [], array $additionalHeaders = []): array
    {
        $url = $this->buildUrl($endpoint);
        $headers = array_merge($this->defaultHeaders, $additionalHeaders);

        try {
            $startTime = microtime(true);
            $response = $this->sendRequest('POST', $url, $headers, json_encode($body));
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $data = $this->responseParser->parse($response);

            $this->logger->info('OpenAPI POST request successful', [
                'endpoint' => $endpoint,
                'response_time_ms' => $responseTime,
                'status_code' => $response->getStatusCode(),
            ]);

            return $data;

        } catch (GuzzleException $e) {
            return $this->handleRequestError($e, 'POST', $endpoint);
        }
    }

    /**
     * Perform PUT request to OpenAPI endpoint
     *
     * @param string $endpoint API endpoint path
     * @param array $body Request body data
     * @param array $additionalHeaders Additional headers
     * @return array Parsed response data
     * @throws OpenApiException
     */
    public function put(string $endpoint, array $body = [], array $additionalHeaders = []): array
    {
        $url = $this->buildUrl($endpoint);
        $headers = array_merge($this->defaultHeaders, $additionalHeaders);

        try {
            $response = $this->sendRequest('PUT', $url, $headers, json_encode($body));
            return $this->responseParser->parse($response);

        } catch (GuzzleException $e) {
            return $this->handleRequestError($e, 'PUT', $endpoint);
        }
    }

    /**
     * Perform DELETE request to OpenAPI endpoint
     *
     * @param string $endpoint API endpoint path
     * @param array $additionalHeaders Additional headers
     * @return array Parsed response data
     * @throws OpenApiException
     */
    public function delete(string $endpoint, array $additionalHeaders = []): array
    {
        $url = $this->buildUrl($endpoint);
        $headers = array_merge($this->defaultHeaders, $additionalHeaders);

        try {
            $response = $this->sendRequest('DELETE', $url, $headers);
            return $this->responseParser->parse($response);

        } catch (GuzzleException $e) {
            return $this->handleRequestError($e, 'DELETE', $endpoint);
        }
    }

    /**
     * Send HTTP request with retry logic
     *
     * @param string $method HTTP method
     * @param string $url Full URL
     * @param array $headers Request headers
     * @param string|null $body Request body
     * @return ResponseInterface
     * @throws GuzzleException
     */
    private function sendRequest(string $method, string $url, array $headers, ?string $body = null): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);

        // Add headers
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Add body if provided
        if ($body !== null) {
            $stream = $this->streamFactory->createStream($body);
            $request = $request->withBody($stream);
        }

        // Retry logic for transient errors
        $lastException = null;
        for ($attempt = 1; $attempt <= self::MAX_RETRY_ATTEMPTS; $attempt++) {
            try {
                return $this->httpClient->sendRequest($request);
            } catch (GuzzleException $e) {
                $lastException = $e;

                // Check if error is retryable (5xx errors, timeouts)
                $statusCode = $e->getResponse()?->getStatusCode() ?? 0;
                if (!$this->isRetryableError($statusCode) || $attempt === self::MAX_RETRY_ATTEMPTS) {
                    throw $e;
                }

                // Exponential backoff
                $delay = pow(2, $attempt - 1);
                $this->logger->warning('OpenAPI request failed, retrying', [
                    'attempt' => $attempt,
                    'delay_seconds' => $delay,
                    'error' => $e->getMessage(),
                ]);
                sleep($delay);
            }
        }

        throw $lastException;
    }

    /**
     * Build full URL from endpoint and query parameters
     */
    private function buildUrl(string $endpoint, array $queryParams = []): string
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        return $url;
    }

    /**
     * Generate cache key for request
     */
    private function generateCacheKey(string $method, string $url, array $params = []): string
    {
        return 'openapi_' . sha1($method . $url . serialize($params));
    }

    /**
     * Get cached response if available
     */
    private function getCachedResponse(string $cacheKey): ?array
    {
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        return null;
    }

    /**
     * Cache response data
     */
    private function cacheResponse(string $cacheKey, array $data): void
    {
        $serialized = serialize($data);

        // Only cache if data meets minimum size
        if (strlen($serialized) >= self::MIN_CACHE_SIZE) {
            $this->cache->set($cacheKey, $data, [], self::CACHE_LIFETIME);
        }
    }

    /**
     * Check if error status code is retryable
     */
    private function isRetryableError(int $statusCode): bool
    {
        return $statusCode >= 500 || $statusCode === 429 || $statusCode === 0;
    }

    /**
     * Handle request errors and throw appropriate exceptions
     *
     * @throws OpenApiException
     */
    private function handleRequestError(GuzzleException $e, string $method, string $endpoint): never
    {
        $statusCode = $e->getResponse()?->getStatusCode() ?? 0;
        $responseBody = $e->getResponse()?->getBody()->getContents() ?? '';

        $this->logger->error('OpenAPI request failed', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'error' => $e->getMessage(),
            'response' => $responseBody,
        ]);

        // Check if token refresh is needed (401 Unauthorized)
        if ($statusCode === 401) {
            $this->logger->info('Attempting token refresh after 401 error');
            if ($this->authenticator->refreshToken()) {
                $this->bearerToken = $this->authenticator->getAccessToken();
                $this->defaultHeaders['Authorization'] = 'Bearer ' . $this->bearerToken;
                // Caller should retry the request
            }
        }

        throw new OpenApiException(
            sprintf('OpenAPI %s request to %s failed: %s', $method, $endpoint, $e->getMessage()),
            $statusCode,
            $e
        );
    }

    /**
     * Refresh authentication token and update headers
     */
    public function refreshAuthentication(): bool
    {
        if ($this->authenticator->refreshToken()) {
            $this->bearerToken = $this->authenticator->getAccessToken();
            $this->defaultHeaders['Authorization'] = 'Bearer ' . $this->bearerToken;
            return true;
        }
        return false;
    }

    /**
     * Clear all cached responses
     */
    public function clearCache(): void
    {
        $this->cache->flush();
        $this->logger->info('OpenAPI cache cleared');
    }
}
