<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

use Univie\UniviePure\Service\OpenApi\OpenApiService;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating API service instances
 *
 * Determines which API implementation to use based on configuration.
 * Supports feature flags for gradual migration from XML to OpenAPI.
 */
class ApiServiceFactory
{
    private const API_VERSION_XML = 'xml';
    private const API_VERSION_OPENAPI = 'openapi';
    private const DEFAULT_API_VERSION = self::API_VERSION_XML;

    public function __construct(
        private readonly XmlApiService $xmlApiService,
        private readonly OpenApiService $openApiService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Create appropriate API service based on configuration
     *
     * @return ApiServiceInterface
     */
    public function create(): ApiServiceInterface
    {
        $apiVersion = $this->getConfiguredApiVersion();

        $this->logger->info('Creating API service', [
            'api_version' => $apiVersion,
        ]);

        return match ($apiVersion) {
            self::API_VERSION_OPENAPI => $this->openApiService,
            self::API_VERSION_XML => $this->xmlApiService,
            default => $this->getDefaultService(),
        };
    }

    /**
     * Get configured API version from environment
     *
     * @return string 'xml' or 'openapi'
     */
    private function getConfiguredApiVersion(): string
    {
        // Check environment variable
        $envVersion = $_ENV['PURE_API_VERSION'] ?? null;

        if ($envVersion !== null) {
            $envVersion = strtolower($envVersion);

            if (in_array($envVersion, [self::API_VERSION_XML, self::API_VERSION_OPENAPI], true)) {
                return $envVersion;
            }

            $this->logger->warning('Invalid PURE_API_VERSION configured', [
                'configured_value' => $envVersion,
                'allowed_values' => [self::API_VERSION_XML, self::API_VERSION_OPENAPI],
                'using_default' => self::DEFAULT_API_VERSION,
            ]);
        }

        return self::DEFAULT_API_VERSION;
    }

    /**
     * Get default API service (fallback)
     *
     * @return ApiServiceInterface
     */
    private function getDefaultService(): ApiServiceInterface
    {
        $this->logger->info('Using default API service', [
            'service_type' => self::DEFAULT_API_VERSION,
        ]);

        return $this->xmlApiService;
    }

    /**
     * Check if OpenAPI is enabled
     *
     * @return bool
     */
    public function isOpenApiEnabled(): bool
    {
        return $this->getConfiguredApiVersion() === self::API_VERSION_OPENAPI;
    }

    /**
     * Check if XML API is enabled
     *
     * @return bool
     */
    public function isXmlApiEnabled(): bool
    {
        return $this->getConfiguredApiVersion() === self::API_VERSION_XML;
    }

    /**
     * Get the OpenAPI service directly (for testing/admin purposes)
     *
     * @return OpenApiService
     */
    public function getOpenApiService(): OpenApiService
    {
        return $this->openApiService;
    }

    /**
     * Get the XML API service directly (for testing/admin purposes)
     *
     * @return XmlApiService
     */
    public function getXmlApiService(): XmlApiService
    {
        return $this->xmlApiService;
    }
}
