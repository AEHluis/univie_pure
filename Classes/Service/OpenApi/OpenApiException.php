<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\OpenApi;

use RuntimeException;
use Throwable;

/**
 * Exception thrown for OpenAPI-related errors
 *
 * Provides structured error information including HTTP status codes
 * and detailed error messages from the API.
 */
class OpenApiException extends RuntimeException
{
    private int $httpStatusCode;
    private ?array $errorData = null;

    /**
     * @param string $message Error message
     * @param int $httpStatusCode HTTP status code (0 if not applicable)
     * @param Throwable|null $previous Previous exception
     * @param array|null $errorData Additional error data from API
     */
    public function __construct(
        string $message = '',
        int $httpStatusCode = 0,
        ?Throwable $previous = null,
        ?array $errorData = null
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->errorData = $errorData;

        parent::__construct($message, $httpStatusCode, $previous);
    }

    /**
     * Get HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * Get additional error data from API
     */
    public function getErrorData(): ?array
    {
        return $this->errorData;
    }

    /**
     * Check if error is due to authentication failure
     */
    public function isAuthenticationError(): bool
    {
        return $this->httpStatusCode === 401;
    }

    /**
     * Check if error is due to authorization failure
     */
    public function isAuthorizationError(): bool
    {
        return $this->httpStatusCode === 403;
    }

    /**
     * Check if error is due to resource not found
     */
    public function isNotFoundError(): bool
    {
        return $this->httpStatusCode === 404;
    }

    /**
     * Check if error is due to rate limiting
     */
    public function isRateLimitError(): bool
    {
        return $this->httpStatusCode === 429;
    }

    /**
     * Check if error is a server error (5xx)
     */
    public function isServerError(): bool
    {
        return $this->httpStatusCode >= 500 && $this->httpStatusCode < 600;
    }

    /**
     * Check if error is a client error (4xx)
     */
    public function isClientError(): bool
    {
        return $this->httpStatusCode >= 400 && $this->httpStatusCode < 500;
    }

    /**
     * Get user-friendly error message
     */
    public function getUserMessage(): string
    {
        return match (true) {
            $this->isAuthenticationError() => 'Authentication failed. Please check your API credentials.',
            $this->isAuthorizationError() => 'Access denied. You do not have permission to access this resource.',
            $this->isNotFoundError() => 'The requested resource was not found.',
            $this->isRateLimitError() => 'Rate limit exceeded. Please try again later.',
            $this->isServerError() => 'The API server encountered an error. Please try again later.',
            default => $this->getMessage(),
        };
    }

    /**
     * Convert exception to array for logging/debugging
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'http_status_code' => $this->httpStatusCode,
            'error_data' => $this->errorData,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }
}
