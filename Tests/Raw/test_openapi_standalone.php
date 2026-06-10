#!/usr/bin/env php
<?php

/**
 * Standalone OpenAPI Test Script
 *
 * Tests Pure OpenAPI endpoints - reads from .env or uses defaults
 *
 * Usage: php test_openapi_standalone.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "========================================\n";
echo "Pure OpenAPI - Standalone Test Script\n";
echo "========================================\n\n";

// Try to load .env file
function loadEnv(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $vars = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and empty lines
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            $vars[$key] = $value;
        }
    }

    return $vars;
}

// Load environment variables
$envFile = __DIR__ . '/.env';
$envVars = loadEnv($envFile);

echo "Loading configuration...\n";
if (!empty($envVars)) {
    echo "✅ Loaded .env file from: $envFile\n";
} else {
    echo "⚠️  No .env file found, using defaults\n";
}

// Configuration with fallbacks
$config = [
    'api_key' => $envVars['PURE_OPENAPI_KEY'] ?? $envVars['PURE_API_KEY'] ?? '3420b029-821c-44aa-9371-73b3067b42f8',
    'base_url' => $envVars['PURE_OPENAPI_URL'] ?? 'https://fis.uni-hannover.de/ws/api',
    'proxy' => $envVars['PURE_PROXY'] ?? 'http://proxy.luis.uni-hannover.de:3128',
    'output_dir' => __DIR__ . '/openapi_test_results',
    'timeout' => 30,
];

echo "\nConfiguration:\n";
echo "  API Key: " . substr($config['api_key'], 0, 8) . "..." . substr($config['api_key'], -8) . "\n";
echo "  Base URL: {$config['base_url']}\n";
echo "  Proxy: {$config['proxy']}\n";
echo "  Output Dir: {$config['output_dir']}\n";
echo "========================================\n\n";

// Create output directory
if (!is_dir($config['output_dir'])) {
    mkdir($config['output_dir'], 0755, true);
}

/**
 * Make API request via proxy
 */
function makeApiRequest(array $config, string $endpoint, array $queryParams = []): array
{
    $url = rtrim($config['base_url'], '/') . '/' . ltrim($endpoint, '/');

    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => $config['timeout'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_PROXY => $config['proxy'],
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $config['api_key'],
            'User-Agent: TYPO3-UniviePure-OpenAPI-Test/1.0',
        ],
    ]);

    $startTime = microtime(true);
    $response = curl_exec($ch);
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300 && empty($curlError),
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'response_time_ms' => $responseTime,
        'url' => $effectiveUrl,
        'curl_error' => $curlError ?: null,
        'response' => $response,
        'response_data' => json_decode($response, true),
        'json_error' => json_last_error_msg(),
    ];
}

/**
 * Test an endpoint and save results
 */
function testEndpoint(array $config, string $name, string $endpoint, array $queryParams = [], string $description = ''): array
{
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "Testing: $name\n";
    echo str_repeat("=", 70) . "\n";
    echo "Description: $description\n";
    echo "Endpoint: $endpoint\n";
    echo "Query Params: " . json_encode($queryParams) . "\n";
    echo str_repeat("-", 70) . "\n";

    $result = makeApiRequest($config, $endpoint, $queryParams);

    // Display summary
    $statusIcon = $result['success'] ? '✅' : '❌';
    echo "$statusIcon Status: HTTP {$result['http_code']}\n";
    echo "⏱️  Response Time: {$result['response_time_ms']}ms\n";
    echo "📄 Content-Type: {$result['content_type']}\n";

    if ($result['curl_error']) {
        echo "❌ cURL Error: {$result['curl_error']}\n";
    }

    if (!$result['success']) {
        // Show error details
        echo "❌ Error Response Preview:\n";
        echo substr($result['response'], 0, 500) . "\n";
    } elseif ($result['response_data']) {
        $data = $result['response_data'];

        // Display data summary
        if (isset($data['count'])) {
            echo "📊 Total Count: {$data['count']}\n";
        }

        if (isset($data['items'])) {
            $itemCount = count($data['items']);
            echo "📦 Items Returned: $itemCount\n";

            if ($itemCount > 0) {
                $firstItem = $data['items'][0];
                echo "🔑 First Item Keys: " . implode(', ', array_keys($firstItem)) . "\n";

                if (isset($firstItem['uuid'])) {
                    echo "🆔 First Item UUID: {$firstItem['uuid']}\n";
                }

                // Display title/name
                $title = $firstItem['title'] ?? $firstItem['name'] ?? null;
                if ($title) {
                    if (is_array($title)) {
                        $titleText = $title['value'] ?? json_encode($title);
                    } else {
                        $titleText = $title;
                    }
                    echo "📌 First Item: " . substr($titleText, 0, 80) . "\n";
                }
            }
        } elseif (isset($data['uuid'])) {
            // Single item response
            echo "🔑 Response Keys: " . implode(', ', array_keys($data)) . "\n";
            echo "🆔 UUID: {$data['uuid']}\n";
        }

        if (isset($data['pageInformation'])) {
            $page = $data['pageInformation'];
            echo "📄 Pagination: offset={$page['offset']}, size={$page['size']}\n";
        }
    }

    // Save to file
    $filename = preg_replace('/[^a-z0-9_-]/i', '_', $name) . '.json';
    $filepath = $config['output_dir'] . '/' . $filename;

    $outputData = [
        'test_name' => $name,
        'description' => $description,
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'query_params' => $queryParams,
        'result' => $result,
    ];

    file_put_contents($filepath, json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "💾 Saved to: $filename\n";

    return $result;
}

// =============================================================================
// QUICK CONNECTIVITY TEST
// =============================================================================

echo "Running quick connectivity test...\n";
$quickTest = makeApiRequest($config, 'research-outputs', ['size' => 1]);

if (!$quickTest['success']) {
    echo "\n❌ CONNECTIVITY TEST FAILED!\n";
    echo "HTTP Code: {$quickTest['http_code']}\n";
    echo "Error: {$quickTest['curl_error']}\n";
    echo "\nResponse preview:\n";
    echo substr($quickTest['response'], 0, 500) . "\n\n";

    echo "Please check:\n";
    echo "1. API Key is correct: " . substr($config['api_key'], 0, 8) . "...{$config['api_key']}\n";
    echo "2. Base URL is correct: {$config['base_url']}\n";
    echo "3. Proxy is accessible: {$config['proxy']}\n";
    echo "4. You're running from development server\n\n";

    echo "Try manual test:\n";
    echo "curl -v -x {$config['proxy']} \\\n";
    echo "  -H \"Accept: application/json\" \\\n";
    echo "  -H \"api-key: {$config['api_key']}\" \\\n";
    echo "  \"{$config['base_url']}/research-outputs?size=1\"\n\n";

    exit(1);
}

echo "✅ Connectivity test successful!\n";
echo "Proceeding with full test suite...\n\n";

// =============================================================================
// TEST SUITE
// =============================================================================

$testResults = [];
$startTime = microtime(true);

// -----------------------------------------------------------------------------
// 1. RESEARCH OUTPUTS
// -----------------------------------------------------------------------------

$testResults['research_outputs_list'] = testEndpoint(
    $config,
    'research_outputs_list',
    'research-outputs',
    ['size' => 5, 'offset' => 0],
    'List research outputs with pagination'
);

$testResults['research_outputs_search'] = testEndpoint(
    $config,
    'research_outputs_search',
    'research-outputs',
    ['size' => 3, 'q' => 'machine learning'],
    'Search research outputs by keyword'
);

$testResults['research_outputs_filter_year'] = testEndpoint(
    $config,
    'research_outputs_filter_year',
    'research-outputs',
    ['size' => 3, 'publicationYearFrom' => 2023],
    'Filter research outputs by publication year'
);

// Get first UUID for single item test
if ($testResults['research_outputs_list']['success']
    && !empty($testResults['research_outputs_list']['response_data']['items'])) {
    $firstResearchOutputUuid = $testResults['research_outputs_list']['response_data']['items'][0]['uuid'];

    $testResults['research_output_single'] = testEndpoint(
        $config,
        'research_output_single',
        "research-outputs/{$firstResearchOutputUuid}",
        [],
        'Get single research output by UUID'
    );
}

// -----------------------------------------------------------------------------
// 2. PERSONS
// -----------------------------------------------------------------------------

$testResults['persons_list'] = testEndpoint(
    $config,
    'persons_list',
    'persons',
    ['size' => 5, 'offset' => 0],
    'List persons with pagination'
);

$testResults['persons_search'] = testEndpoint(
    $config,
    'persons_search',
    'persons',
    ['size' => 3, 'q' => 'Schmidt'],
    'Search persons by name'
);

// Get first UUID for single item test
if ($testResults['persons_list']['success']
    && !empty($testResults['persons_list']['response_data']['items'])) {
    $firstPersonUuid = $testResults['persons_list']['response_data']['items'][0]['uuid'];

    $testResults['person_single'] = testEndpoint(
        $config,
        'person_single',
        "persons/{$firstPersonUuid}",
        [],
        'Get single person by UUID'
    );
}

// -----------------------------------------------------------------------------
// 3. PROJECTS
// -----------------------------------------------------------------------------

$testResults['projects_list'] = testEndpoint(
    $config,
    'projects_list',
    'projects',
    ['size' => 5, 'offset' => 0],
    'List projects with pagination'
);

$testResults['projects_search'] = testEndpoint(
    $config,
    'projects_search',
    'projects',
    ['size' => 3, 'q' => 'research'],
    'Search projects by keyword'
);

if ($testResults['projects_list']['success']
    && !empty($testResults['projects_list']['response_data']['items'])) {
    $firstProjectUuid = $testResults['projects_list']['response_data']['items'][0]['uuid'];

    $testResults['project_single'] = testEndpoint(
        $config,
        'project_single',
        "projects/{$firstProjectUuid}",
        [],
        'Get single project by UUID'
    );
}

// -----------------------------------------------------------------------------
// 4. ORGANIZATIONAL UNITS
// -----------------------------------------------------------------------------

$testResults['organizational_units_list'] = testEndpoint(
    $config,
    'organizational_units_list',
    'organizations',
    ['size' => 5, 'offset' => 0],
    'List organizational units with pagination'
);

if ($testResults['organizational_units_list']['success']
    && !empty($testResults['organizational_units_list']['response_data']['items'])) {
    $firstOrgUnitUuid = $testResults['organizational_units_list']['response_data']['items'][0]['uuid'];

    $testResults['organizational_unit_single'] = testEndpoint(
        $config,
        'organizational_unit_single',
        "organizations/{$firstOrgUnitUuid}",
        [],
        'Get single organizational unit by UUID'
    );
}

// =============================================================================
// SUMMARY REPORT
// =============================================================================

$totalTime = round((microtime(true) - $startTime) * 1000, 2);

echo "\n\n";
echo str_repeat("=", 70) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat("=", 70) . "\n";

$successCount = 0;
$failureCount = 0;
$totalRequests = count($testResults);

foreach ($testResults as $name => $result) {
    if ($result['success']) {
        $successCount++;
        echo "✅ $name\n";
    } else {
        $failureCount++;
        echo "❌ $name (HTTP {$result['http_code']})\n";
    }
}

echo "\n";
echo "Total Tests: $totalRequests\n";
echo "✅ Successful: $successCount\n";
echo "❌ Failed: $failureCount\n";
echo "⏱️  Total Time: {$totalTime}ms\n";
echo "📁 Results saved to: {$config['output_dir']}/\n";

// Create summary file
$summaryFile = $config['output_dir'] . '/_summary.json';
$summary = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_tests' => $totalRequests,
    'successful' => $successCount,
    'failed' => $failureCount,
    'total_time_ms' => $totalTime,
    'config' => [
        'base_url' => $config['base_url'],
        'proxy' => $config['proxy'],
        'api_key' => substr($config['api_key'], 0, 8) . '...' . substr($config['api_key'], -8),
    ],
    'test_results' => array_map(function($result, $name) {
        return [
            'name' => $name,
            'success' => $result['success'],
            'http_code' => $result['http_code'],
            'response_time_ms' => $result['response_time_ms'],
            'has_data' => !empty($result['response_data']),
            'item_count' => $result['response_data']['count'] ?? null,
        ];
    }, $testResults, array_keys($testResults)),
];

file_put_contents($summaryFile, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "\n";
echo str_repeat("=", 70) . "\n";
if ($successCount > 0) {
    echo "✅ Testing completed successfully!\n";
} else {
    echo "❌ All tests failed - please check configuration\n";
}
echo "📊 Summary saved to: _summary.json\n";
echo str_repeat("=", 70) . "\n";
