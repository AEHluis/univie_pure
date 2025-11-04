<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\OpenApi;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Parser for OpenAPI JSON responses
 *
 * Handles parsing, validation, and error checking of JSON responses from the Pure OpenAPI.
 */
class OpenApiResponseParser
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Parse HTTP response to array
     *
     * @param ResponseInterface $response HTTP response object
     * @return array Parsed response data
     * @throws OpenApiException If response cannot be parsed or contains errors
     */
    public function parse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $contentType = $response->getHeaderLine('Content-Type');
        $body = $response->getBody()->getContents();

        // Handle empty responses
        if (empty($body)) {
            if ($statusCode >= 200 && $statusCode < 300) {
                return []; // Successful empty response (e.g., 204 No Content)
            }
            throw new OpenApiException('Empty response received', $statusCode);
        }

        // Verify content type
        if (!str_contains($contentType, 'application/json')) {
            $this->logger->warning('Unexpected content type', [
                'expected' => 'application/json',
                'received' => $contentType,
                'status_code' => $statusCode,
            ]);
        }

        // Decode JSON
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new OpenApiException(
                'JSON decode error: ' . json_last_error_msg(),
                $statusCode
            );
        }

        // Check for API errors in response
        $this->checkForErrors($data, $statusCode);

        return $data;
    }

    /**
     * Check response data for API errors
     *
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @throws OpenApiException If error is detected in response
     */
    private function checkForErrors(array $data, int $statusCode): void
    {
        // Check for standard error response format
        if (isset($data['error'])) {
            $errorMessage = $data['error']['message'] ?? $data['error'];
            $errorCode = $data['error']['code'] ?? $statusCode;

            throw new OpenApiException(
                sprintf('API Error: %s', $errorMessage),
                (int)$errorCode
            );
        }

        // Check for errors array (multiple errors)
        if (isset($data['errors']) && is_array($data['errors'])) {
            $errorMessages = array_map(function ($error) {
                return is_array($error) ? ($error['message'] ?? json_encode($error)) : $error;
            }, $data['errors']);

            throw new OpenApiException(
                'API Errors: ' . implode(', ', $errorMessages),
                $statusCode
            );
        }

        // Check for HTTP error status codes
        if ($statusCode >= 400) {
            $message = $data['message'] ?? $data['detail'] ?? 'HTTP Error';
            throw new OpenApiException(
                sprintf('HTTP %d: %s', $statusCode, $message),
                $statusCode
            );
        }
    }

    /**
     * Extract pagination information from response
     *
     * @param array $response Full response data
     * @return array Pagination metadata
     */
    public function extractPagination(array $response): array
    {
        $pagination = [
            'total' => 0,
            'count' => 0,
            'offset' => 0,
            'limit' => 0,
            'has_more' => false,
            'next_url' => null,
            'prev_url' => null,
        ];

        // Check for different pagination formats
        if (isset($response['count'])) {
            $pagination['total'] = $response['count'];
        } elseif (isset($response['total'])) {
            $pagination['total'] = $response['total'];
        }

        if (isset($response['items'])) {
            $pagination['count'] = count($response['items']);
        } elseif (isset($response['data'])) {
            $pagination['count'] = count($response['data']);
        }

        // Extract pagination metadata
        if (isset($response['pageInformation'])) {
            $pageInfo = $response['pageInformation'];
            $pagination['offset'] = $pageInfo['offset'] ?? 0;
            $pagination['limit'] = $pageInfo['size'] ?? 0;
        }

        // Check for next/previous page URLs
        if (isset($response['links'])) {
            $pagination['next_url'] = $response['links']['next'] ?? null;
            $pagination['prev_url'] = $response['links']['prev'] ?? null;
        }

        $pagination['has_more'] = $pagination['next_url'] !== null ||
            ($pagination['offset'] + $pagination['count'] < $pagination['total']);

        return $pagination;
    }

    /**
     * Extract items/data array from response
     *
     * Different endpoints may use different keys for the data array.
     *
     * @param array $response Full response data
     * @return array Items array
     */
    public function extractItems(array $response): array
    {
        // Common data keys in order of preference
        $dataKeys = ['items', 'data', 'results', 'content'];

        foreach ($dataKeys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        // If no standard key found, return the whole response
        // (might be a single item response)
        return $response;
    }

    /**
     * Parse collection response (list of items)
     *
     * @param array $response Full response data
     * @return array Normalized collection data with items and pagination
     */
    public function parseCollection(array $response): array
    {
        return [
            'items' => $this->extractItems($response),
            'pagination' => $this->extractPagination($response),
            'meta' => $this->extractMetadata($response),
        ];
    }

    /**
     * Extract metadata from response
     *
     * @param array $response Full response data
     * @return array Metadata
     */
    public function extractMetadata(array $response): array
    {
        $metadata = [];

        // Extract any metadata fields
        $metadataKeys = ['_meta', 'metadata', 'meta'];

        foreach ($metadataKeys as $key) {
            if (isset($response[$key])) {
                $metadata = array_merge($metadata, (array)$response[$key]);
            }
        }

        return $metadata;
    }

    /**
     * Validate required fields in response data
     *
     * @param array $data Response data
     * @param array $requiredFields List of required field names
     * @throws OpenApiException If required fields are missing
     */
    public function validateRequiredFields(array $data, array $requiredFields): void
    {
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new OpenApiException(
                'Missing required fields: ' . implode(', ', $missingFields),
                400
            );
        }
    }
}
