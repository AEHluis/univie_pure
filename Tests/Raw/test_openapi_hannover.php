#!/usr/bin/env php
<?php

/**
 * OpenAPI Connection Test Script for Uni Hannover Pure Instance
 *
 * Tests the Pure OpenAPI connection with the Hannover instance
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Configuration for Uni Hannover
$apiKey = '18c2b55b-135f-4072-b0bc-8f8eb74df30c';
$baseUrl = 'https://fis.uni-hannover.de/ws/api';

echo "========================================\n";
echo "Pure OpenAPI Connection Test\n";
echo "Uni Hannover FIS Instance\n";
echo "========================================\n";
echo "Base URL: $baseUrl\n";
echo "API Key: " . substr($apiKey, 0, 8) . "..." . substr($apiKey, -8) . "\n";
echo "========================================\n\n";

/**
 * Test function to make API requests
 */
function testApiRequest(string $endpoint, string $baseUrl, string $apiKey): void
{
    echo "Testing endpoint: $endpoint\n";
    echo str_repeat("-", 60) . "\n";

    $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

    // Initialize cURL
    $ch = curl_init($url);

    // Set cURL options
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $apiKey,
            'User-Agent: TYPO3-UniviePure-OpenAPI-Test/1.0',
        ],
    ]);

    // Execute request
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);

    // Get response info
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);

    curl_close($ch);

    // Display results
    echo "HTTP Status: $httpCode\n";
    echo "Content-Type: $contentType\n";
    echo "Response Time: {$responseTime}ms\n";

    if ($error) {
        echo "❌ cURL Error: $error\n";
    } else {
        if ($httpCode >= 200 && $httpCode < 300) {
            echo "✅ Request successful!\n";

            // Parse and display JSON response
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "\nResponse structure:\n";

                // Display collection info if available
                if (isset($data['count'])) {
                    echo "  - Total items: {$data['count']}\n";
                }
                if (isset($data['items'])) {
                    echo "  - Returned items: " . count($data['items']) . "\n";

                    // Display first item details
                    if (!empty($data['items'])) {
                        $firstItem = $data['items'][0];
                        echo "  - First item keys: " . implode(', ', array_keys($firstItem)) . "\n";

                        // Display UUID
                        if (isset($firstItem['uuid'])) {
                            echo "  - First item UUID: {$firstItem['uuid']}\n";
                        }

                        // Display name/title if available
                        if (isset($firstItem['name'])) {
                            $name = is_array($firstItem['name']) ? json_encode($firstItem['name']) : $firstItem['name'];
                            echo "  - First item name: " . substr($name, 0, 100) . "\n";
                        }
                        if (isset($firstItem['title'])) {
                            $title = is_array($firstItem['title']) ? json_encode($firstItem['title']) : $firstItem['title'];
                            echo "  - First item title: " . substr($title, 0, 100) . "\n";
                        }
                    }
                }

                // Display pagination info
                if (isset($data['pageInformation'])) {
                    echo "  - Pagination: offset={$data['pageInformation']['offset']}, size={$data['pageInformation']['size']}\n";
                }

                // Pretty print first 800 chars of response
                $prettyJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (strlen($prettyJson) > 800) {
                    echo "\nResponse preview (first 800 chars):\n";
                    echo substr($prettyJson, 0, 800) . "...\n";
                } else {
                    echo "\nFull response:\n";
                    echo $prettyJson . "\n";
                }
            } else {
                echo "\n⚠️ Response is not valid JSON:\n";
                echo substr($response, 0, 500) . "\n";
            }
        } elseif ($httpCode === 401) {
            echo "❌ Authentication failed - Invalid API key\n";
        } elseif ($httpCode === 403) {
            echo "❌ Access forbidden - API key lacks permissions\n";
        } elseif ($httpCode === 404) {
            echo "❌ Endpoint not found\n";
        } else {
            echo "❌ Request failed\n";
            echo "Response: " . substr($response, 0, 200) . "\n";
        }
    }

    echo "\n\n";
}

// Test various endpoints
$endpoints = [
    'research-outputs?size=2',
    'persons?size=2',
    'projects?size=2',
    'organizational-units?size=2',
    'data-sets?size=2',
];

foreach ($endpoints as $endpoint) {
    testApiRequest($endpoint, $baseUrl, $apiKey);
}

echo "========================================\n";
echo "✅ All tests completed!\n";
echo "========================================\n";
