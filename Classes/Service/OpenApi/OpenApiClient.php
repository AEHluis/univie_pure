<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\OpenApi;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Core\Environment;
use Univie\UniviePure\Utility\DotEnv;

/**
 * OpenAPI REST client for Elsevier Pure API
 *
 * Handles HTTP communication with the Pure OpenAPI endpoints.
 * Uses simple API key authentication via 'api-key' header.
 */
class OpenApiClient
{
    private const CACHE_LIFETIME = 14400; // 4 hours
    private const MIN_CACHE_SIZE = 350;
    private const MAX_RETRY_ATTEMPTS = 3;

    private string $baseUrl = '';
    private array $defaultHeaders = [];

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly OpenApiResponseParser $responseParser
    ) {
        $this->initializeConfiguration();
    }

    /**
     * Initialize OpenAPI configuration from environment
     */
    private function initializeConfiguration(): void
    {
        $envVars = $this->loadEnvVariables();

        $this->baseUrl = rtrim($envVars['PURE_OPENAPI_URL'] ?? '', '/');

        $this->defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'TYPO3-UniviePure-OpenAPI/1.0',
        ];

        // Pure API uses 'api-key' header for authentication
        // Check multiple possible env var names for flexibility
        $apiKey = $envVars['PURE_API_KEY']
            ?? $envVars['PURE_OPENAPI_KEY']
            ?? $envVars['PURE_APIKEY']
            ?? $envVars['PURE_BEARER_TOKEN']
            ?? null;

        if ($apiKey) {
            $this->defaultHeaders['api-key'] = $apiKey;
            $this->logger->info('OpenAPI client initialized', [
                'base_url' => $this->baseUrl,
                'has_api_key' => true,
            ]);
        } else {
            $this->logger->warning('No API key configured. Set PURE_API_KEY in .env file.', [
                'base_url' => $this->baseUrl,
            ]);
        }

        if (empty($this->baseUrl)) {
            $this->logger->error('PURE_OPENAPI_URL not configured in .env file');
        }
    }

    /**
     * Load environment variables from .env file and $_ENV
     */
    private function loadEnvVariables(): array
    {
        $envVars = [];

        // Try project root first
        $projectEnvPath = Environment::getProjectPath() . '/.env';

        // Fallback to extension directory
        $extensionEnvPath = dirname(__DIR__, 3) . '/.env';

        $envPaths = [$projectEnvPath, $extensionEnvPath];

        foreach ($envPaths as $envPath) {
            if (file_exists($envPath)) {
                try {
                    $dotEnv = new DotEnv($envPath);
                    $dotEnv->load();
                    $envVars = $dotEnv->variables;
                    $this->logger->debug('Loaded .env file', ['path' => $envPath]);
                    break;
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to load .env file', [
                        'path' => $envPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (empty($envVars)) {
            $this->logger->debug('No .env file found, using $_ENV', [
                'tried_paths' => $envPaths,
            ]);
        }

        // Merge with $_ENV (prefer .env values)
        return array_merge($_ENV, $envVars);
    }

    /**
     * Perform GET request to OpenAPI endpoint
     *
     * @param string $endpoint API endpoint path (e.g., '/persons')
     * @param array $queryParams Query parameters
     * @param array $additionalHeaders Additional headers
     * @return array Parsed response data
     * @throws OpenApiException
     */
    public function get(string $endpoint, array $queryParams = [], array $additionalHeaders = []): array
    {
        $url = $this->buildUrl($endpoint, $queryParams);
        $cacheKey = $this->generateCacheKey('GET', $url, $queryParams);

        if ($cachedResponse = $this->getCachedResponse($cacheKey)) {
            $this->logger->debug('OpenAPI cache hit', ['endpoint' => $endpoint]);
            return $cachedResponse;
        }

        $headers = array_merge($this->defaultHeaders, $additionalHeaders);

        try {
            $startTime = microtime(true);
            $response = $this->sendRequest('GET', $url, $headers);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $data = $this->responseParser->parse($response);
            $this->cacheResponse($cacheKey, $data);

            $this->logger->info('OpenAPI GET request successful', [
                'endpoint' => $endpoint,
                'response_time_ms' => $responseTime,
                'status_code' => $response->getStatusCode(),
            ]);

            return $data;

        } catch (ClientExceptionInterface $e) {
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
            $response = $this->sendRequest('POST', $url, $headers, json_encode($body));
            return $this->responseParser->parse($response);
        } catch (ClientExceptionInterface $e) {
            return $this->handleRequestError($e, 'POST', $endpoint);
        }
    }

    /**
     * Send HTTP request with retry logic
     */
    private function sendRequest(string $method, string $url, array $headers, ?string $body = null): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        $lastException = null;
        for ($attempt = 1; $attempt <= self::MAX_RETRY_ATTEMPTS; $attempt++) {
            try {
                $response = $this->httpClient->sendRequest($request);
                $statusCode = $response->getStatusCode();

                if ($statusCode >= 400) {
                    if ($this->isRetryableError($statusCode) && $attempt < self::MAX_RETRY_ATTEMPTS) {
                        $delay = pow(2, $attempt - 1);
                        $this->logger->warning('OpenAPI request returned error, retrying', [
                            'attempt' => $attempt,
                            'status_code' => $statusCode,
                            'delay_seconds' => $delay,
                        ]);
                        sleep($delay);
                        continue;
                    }
                }

                return $response;

            } catch (ClientExceptionInterface $e) {
                $lastException = $e;

                if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                    $delay = pow(2, $attempt - 1);
                    $this->logger->warning('OpenAPI request failed, retrying', [
                        'attempt' => $attempt,
                        'delay_seconds' => $delay,
                        'error' => $e->getMessage(),
                    ]);
                    sleep($delay);
                } else {
                    throw $e;
                }
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

        $this->logger->debug('OpenAPI URL built', [
            'base_url' => $this->baseUrl,
            'endpoint' => $endpoint,
            'full_url' => $url,
        ]);

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
    private function handleRequestError(ClientExceptionInterface $e, string $method, string $endpoint): never
    {
        $statusCode = 0;
        $responseBody = '';

        if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = $e->getResponse()->getBody()->getContents();
        }

        $this->logger->error('OpenAPI request failed', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'error' => $e->getMessage(),
            'response' => $responseBody,
        ]);

        throw new OpenApiException(
            sprintf('OpenAPI %s request to %s failed: %s', $method, $endpoint, $e->getMessage()),
            $statusCode,
            $e
        );
    }

    /**
     * Clear all cached responses
     */
    public function clearCache(): void
    {
        $this->cache->flush();
        $this->logger->info('OpenAPI cache cleared');
    }

    /**
     * Check if API is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && isset($this->defaultHeaders['api-key']);
    }
}
