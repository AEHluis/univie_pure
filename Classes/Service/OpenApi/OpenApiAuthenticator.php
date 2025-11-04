<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\OpenApi;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Registry;

/**
 * OAuth 2.0 / Bearer Token authenticator for Pure OpenAPI
 *
 * Handles token acquisition, refresh, and storage for OpenAPI authentication.
 * Supports both API key (Bearer token) and OAuth 2.0 client credentials flow.
 */
class OpenApiAuthenticator
{
    private const TOKEN_REGISTRY_NAMESPACE = 'univie_pure_openapi';
    private const TOKEN_KEY = 'access_token';
    private const TOKEN_EXPIRY_KEY = 'token_expiry';
    private const TOKEN_REFRESH_KEY = 'refresh_token';

    // Buffer time before token expiry to proactively refresh (5 minutes)
    private const EXPIRY_BUFFER_SECONDS = 300;

    private ?string $clientId = null;
    private ?string $clientSecret = null;
    private ?string $tokenEndpoint = null;
    private ?string $apiKey = null;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly Registry $registry,
        private readonly LoggerInterface $logger
    ) {
        $this->initializeConfiguration();
    }

    /**
     * Initialize authentication configuration from environment
     */
    private function initializeConfiguration(): void
    {
        // Check for OAuth configuration
        $this->clientId = $_ENV['PURE_OAUTH_CLIENT_ID'] ?? null;
        $this->clientSecret = $_ENV['PURE_OAUTH_CLIENT_SECRET'] ?? null;
        $this->tokenEndpoint = $_ENV['PURE_OAUTH_TOKEN_ENDPOINT'] ?? null;

        // Check for simple API key/Bearer token
        $this->apiKey = $_ENV['PURE_OPENAPI_KEY'] ?? $_ENV['PURE_BEARER_TOKEN'] ?? null;

        if (!$this->apiKey && !($this->clientId && $this->clientSecret)) {
            $this->logger->warning(
                'No OpenAPI authentication configured. Set either PURE_OPENAPI_KEY or PURE_OAUTH_CLIENT_ID/SECRET'
            );
        }
    }

    /**
     * Get current access token
     *
     * Returns cached token if valid, otherwise obtains new token.
     *
     * @return string|null Access token or null if authentication not configured
     */
    public function getAccessToken(): ?string
    {
        // If using simple API key, return it directly
        if ($this->apiKey) {
            return $this->apiKey;
        }

        // Check if we have a valid cached token
        if ($cachedToken = $this->getCachedToken()) {
            return $cachedToken;
        }

        // Obtain new token via OAuth
        return $this->obtainNewToken();
    }

    /**
     * Refresh the access token
     *
     * @return bool True if token was successfully refreshed
     */
    public function refreshToken(): bool
    {
        // If using API key, no refresh needed
        if ($this->apiKey) {
            return true;
        }

        // Try to use refresh token first
        if ($refreshToken = $this->getStoredRefreshToken()) {
            if ($this->refreshWithRefreshToken($refreshToken)) {
                return true;
            }
        }

        // Fall back to obtaining new token
        return $this->obtainNewToken() !== null;
    }

    /**
     * Get cached token if still valid
     */
    private function getCachedToken(): ?string
    {
        $token = $this->registry->get(self::TOKEN_REGISTRY_NAMESPACE, self::TOKEN_KEY);
        $expiry = $this->registry->get(self::TOKEN_REGISTRY_NAMESPACE, self::TOKEN_EXPIRY_KEY);

        if (!$token || !$expiry) {
            return null;
        }

        // Check if token is expired or about to expire
        if (time() >= ($expiry - self::EXPIRY_BUFFER_SECONDS)) {
            $this->logger->debug('Cached token expired or about to expire');
            return null;
        }

        return $token;
    }

    /**
     * Obtain new access token via OAuth 2.0 client credentials flow
     */
    private function obtainNewToken(): ?string
    {
        if (!$this->clientId || !$this->clientSecret) {
            $this->logger->error('OAuth credentials not configured');
            return null;
        }

        $tokenEndpoint = $this->tokenEndpoint ?? $this->buildDefaultTokenEndpoint();

        try {
            $this->logger->info('Requesting new OAuth access token');

            $response = $this->httpClient->request('POST', $tokenEndpoint, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'api', // Adjust scope as needed
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['access_token'])) {
                $this->logger->error('OAuth response missing access_token', ['response' => $data]);
                return null;
            }

            $accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 3600; // Default 1 hour
            $refreshToken = $data['refresh_token'] ?? null;

            // Store token and expiry time
            $this->storeToken($accessToken, $expiresIn, $refreshToken);

            $this->logger->info('OAuth access token obtained successfully', [
                'expires_in' => $expiresIn,
                'has_refresh_token' => $refreshToken !== null,
            ]);

            return $accessToken;

        } catch (GuzzleException $e) {
            $this->logger->error('Failed to obtain OAuth access token', [
                'error' => $e->getMessage(),
                'endpoint' => $tokenEndpoint,
            ]);
            return null;
        }
    }

    /**
     * Refresh token using refresh token
     */
    private function refreshWithRefreshToken(string $refreshToken): bool
    {
        if (!$this->clientId || !$this->clientSecret) {
            return false;
        }

        $tokenEndpoint = $this->tokenEndpoint ?? $this->buildDefaultTokenEndpoint();

        try {
            $this->logger->info('Refreshing access token using refresh token');

            $response = $this->httpClient->request('POST', $tokenEndpoint, [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['access_token'])) {
                return false;
            }

            $accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 3600;
            $newRefreshToken = $data['refresh_token'] ?? $refreshToken;

            $this->storeToken($accessToken, $expiresIn, $newRefreshToken);

            $this->logger->info('Access token refreshed successfully');
            return true;

        } catch (GuzzleException $e) {
            $this->logger->error('Failed to refresh access token', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Store token in TYPO3 registry
     */
    private function storeToken(string $accessToken, int $expiresIn, ?string $refreshToken): void
    {
        $expiryTime = time() + $expiresIn;

        $this->registry->set(self::TOKEN_REGISTRY_NAMESPACE, self::TOKEN_KEY, $accessToken);
        $this->registry->set(self::TOKEN_REGISTRY_NAMESPACE, self::TOKEN_EXPIRY_KEY, $expiryTime);

        if ($refreshToken) {
            $this->registry->set(self::TOKEN_REGISTRY_NAMESPACE, self::TOKEN_REFRESH_KEY, $refreshToken);
        }
    }

    /**
     * Get stored refresh token
     */
    private function getStoredRefreshToken(): ?string
    {
        return $this->registry->get(self::TOKEN_REGISTRY_NAMESPACE, self::TOKEN_REFRESH_KEY);
    }

    /**
     * Build default token endpoint URL
     */
    private function buildDefaultTokenEndpoint(): string
    {
        $baseUrl = $_ENV['PURE_OPENAPI_URL'] ?? '';
        return rtrim($baseUrl, '/') . '/oauth/token';
    }

    /**
     * Clear all stored tokens
     */
    public function clearTokens(): void
    {
        $this->registry->remove(self::TOKEN_REGISTRY_NAMESPACE, self::TOKEN_KEY);
        $this->registry->remove(self::TOKEN_REGISTRY_NAMESPACE, self::TOKEN_EXPIRY_KEY);
        $this->registry->remove(self::TOKEN_REGISTRY_NAMESPACE, self::TOKEN_REFRESH_KEY);

        $this->logger->info('All stored tokens cleared');
    }

    /**
     * Check if authentication is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== null || ($this->clientId !== null && $this->clientSecret !== null);
    }
}
