#!/usr/bin/env php
<?php

/**
 * OpenAPI Authentication Methods Test Script
 *
 * Tests different authentication methods for Pure OpenAPI
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$apiKey = '18c2b55b-135f-4072-b0bc-8f8eb74df30c';
$baseUrl = 'https://api.elsevierpure.com';
$endpoint = '/persons?size=1';

echo "========================================\n";
echo "Pure OpenAPI Authentication Test\n";
echo "========================================\n";
echo "Testing various authentication methods...\n\n";

/**
 * Test function with different auth methods
 */
function testAuthMethod(string $method, string $url, string $apiKey, array $headers): void
{
    echo "Method: $method\n";
    echo str_repeat("-", 60) . "\n";

    // Display headers (sanitized)
    echo "Headers:\n";
    foreach ($headers as $key => $value) {
        if (stripos($key, 'key') !== false || stripos($key, 'auth') !== false) {
            $displayValue = substr($value, 0, 10) . '...' . substr($value, -10);
        } else {
            $displayValue = $value;
        }
        echo "  $key: $displayValue\n";
    }
    echo "\n";

    // Initialize cURL
    $ch = curl_init($url);

    // Build header array for curl
    $curlHeaders = [];
    foreach ($headers as $key => $value) {
        $curlHeaders[] = "$key: $value";
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $curlHeaders,
        CURLOPT_VERBOSE => false,
    ]);

    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);

    curl_close($ch);

    // Display results
    echo "HTTP Status: $httpCode\n";
    echo "Content-Type: $contentType\n";

    if ($error) {
        echo "❌ cURL Error: $error\n";
    } else {
        if ($httpCode >= 200 && $httpCode < 300) {
            echo "✅ Success!\n";
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['count'])) {
                echo "Response: Found {$data['count']} items\n";
            }
        } elseif ($httpCode === 401) {
            echo "❌ Unauthorized\n";
        } elseif ($httpCode === 403) {
            echo "❌ Forbidden\n";
        } elseif ($httpCode === 404) {
            echo "❌ Not Found\n";
        } else {
            echo "❌ Failed\n";
        }

        // Show response preview
        if ($response && strlen($response) < 200) {
            echo "Response: $response\n";
        }
    }

    echo "\n\n";
}

$url = $baseUrl . $endpoint;

// Test 1: api-key header
testAuthMethod(
    "1. api-key header",
    $url,
    $apiKey,
    [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'api-key' => $apiKey,
    ]
);

// Test 2: Authorization Bearer token
testAuthMethod(
    "2. Authorization: Bearer",
    $url,
    $apiKey,
    [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $apiKey,
    ]
);

// Test 3: X-API-Key header
testAuthMethod(
    "3. X-API-Key header",
    $url,
    $apiKey,
    [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'X-API-Key' => $apiKey,
    ]
);

// Test 4: Apikey header
testAuthMethod(
    "4. Apikey header",
    $url,
    $apiKey,
    [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'Apikey' => $apiKey,
    ]
);

// Test 5: Query parameter
$urlWithKey = $url . '&apiKey=' . urlencode($apiKey);
testAuthMethod(
    "5. Query parameter (?apiKey=...)",
    $urlWithKey,
    $apiKey,
    [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ]
);

// Test 6: Query parameter (api-key)
$urlWithKey2 = $url . '&api-key=' . urlencode($apiKey);
testAuthMethod(
    "6. Query parameter (?api-key=...)",
    $urlWithKey2,
    $apiKey,
    [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ]
);

echo "========================================\n";
echo "Test completed!\n";
echo "========================================\n";

// Try to get error details from one of the failed requests
echo "\nTrying to get detailed error info from base URL...\n";
$ch = curl_init($baseUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'api-key: ' . $apiKey,
    ],
]);
$response = curl_exec($ch);
echo "Response from base URL:\n";
echo substr($response, 0, 500) . "\n";
curl_close($ch);
