#!/usr/bin/env php
<?php

/**
 * Comprehensive OpenAPI Endpoint Test Script
 *
 * Tests all Pure OpenAPI endpoints via Uni Hannover proxy
 * and saves responses to JSON files for later analysis
 *
 * Usage: php test_all_openapi_endpoints.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Configuration
$config = [
    'api_key' => '3420b029-821c-44aa-9371-73b3067b42f8',
    'base_url' => 'https://fis.uni-hannover.de/ws/api',  // OpenAPI (ohne /524)
    'base_url_legacy' => 'https://fis.uni-hannover.de/ws/api/524',  // Legacy XML API
    'proxy' => 'http://proxy.luis.uni-hannover.de:3128',
    'output_dir' => __DIR__ . '/openapi_test_results',
    'timeout' => 30,
];

// Create output directory
if (!is_dir($config['output_dir'])) {
    mkdir($config['output_dir'], 0755, true);
}

echo "========================================\n";
echo "Pure OpenAPI - Comprehensive Endpoint Test\n";
echo "========================================\n";
echo "Base URL: {$config['base_url']}\n";
echo "Proxy: {$config['proxy']}\n";
echo "Output Dir: {$config['output_dir']}\n";
echo "API Key: " . substr($config['api_key'], 0, 8) . "..." . substr($config['api_key'], -8) . "\n";
echo "========================================\n\n";

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
        CURLOPT_VERBOSE => false,
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

    if ($result['success'] && $result['response_data']) {
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

    // Get research outputs for this person
    $testResults['person_research_outputs'] = testEndpoint(
        $config,
        'person_research_outputs',
        'research-outputs',
        ['size' => 3, 'personUuids' => $firstPersonUuid],
        'Get research outputs for specific person'
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

// Get first UUID for single item test
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
    'organizational-units',
    ['size' => 5, 'offset' => 0],
    'List organizational units with pagination'
);

$testResults['organizational_units_search'] = testEndpoint(
    $config,
    'organizational_units_search',
    'organizational-units',
    ['size' => 3, 'q' => 'Institut'],
    'Search organizational units by name'
);

// Get first UUID for single item test
if ($testResults['organizational_units_list']['success']
    && !empty($testResults['organizational_units_list']['response_data']['items'])) {
    $firstOrgUnitUuid = $testResults['organizational_units_list']['response_data']['items'][0]['uuid'];

    $testResults['organizational_unit_single'] = testEndpoint(
        $config,
        'organizational_unit_single',
        "organizational-units/{$firstOrgUnitUuid}",
        [],
        'Get single organizational unit by UUID'
    );
}

// -----------------------------------------------------------------------------
// 5. DATA SETS
// -----------------------------------------------------------------------------

$testResults['data_sets_list'] = testEndpoint(
    $config,
    'data_sets_list',
    'data-sets',
    ['size' => 5, 'offset' => 0],
    'List data sets with pagination'
);

$testResults['data_sets_search'] = testEndpoint(
    $config,
    'data_sets_search',
    'data-sets',
    ['size' => 3, 'q' => 'data'],
    'Search data sets by keyword'
);

// Get first UUID for single item test (if available)
if ($testResults['data_sets_list']['success']
    && !empty($testResults['data_sets_list']['response_data']['items'])) {
    $firstDataSetUuid = $testResults['data_sets_list']['response_data']['items'][0]['uuid'];

    $testResults['data_set_single'] = testEndpoint(
        $config,
        'data_set_single',
        "data-sets/{$firstDataSetUuid}",
        [],
        'Get single data set by UUID'
    );
}

// -----------------------------------------------------------------------------
// 6. EQUIPMENTS
// -----------------------------------------------------------------------------

$testResults['equipments_list'] = testEndpoint(
    $config,
    'equipments_list',
    'equipments',
    ['size' => 5, 'offset' => 0],
    'List equipments with pagination'
);

$testResults['equipments_search'] = testEndpoint(
    $config,
    'equipments_search',
    'equipments',
    ['size' => 3, 'q' => 'microscope'],
    'Search equipments by keyword'
);

// Get first UUID for single item test (if available)
if ($testResults['equipments_list']['success']
    && !empty($testResults['equipments_list']['response_data']['items'])) {
    $firstEquipmentUuid = $testResults['equipments_list']['response_data']['items'][0]['uuid'];

    $testResults['equipment_single'] = testEndpoint(
        $config,
        'equipment_single',
        "equipments/{$firstEquipmentUuid}",
        [],
        'Get single equipment by UUID'
    );
}

// -----------------------------------------------------------------------------
// 7. ADVANCED QUERIES
// -----------------------------------------------------------------------------

$testResults['research_outputs_sorting'] = testEndpoint(
    $config,
    'research_outputs_sorting',
    'research-outputs',
    ['size' => 3, 'order' => '-publicationYear'],
    'Research outputs sorted by year (descending)'
);

$testResults['research_outputs_pagination'] = testEndpoint(
    $config,
    'research_outputs_pagination',
    'research-outputs',
    ['size' => 2, 'offset' => 10],
    'Research outputs with offset pagination'
);

// Test locale/language support
$testResults['research_outputs_locale_de'] = testEndpoint(
    $config,
    'research_outputs_locale_de',
    'research-outputs',
    ['size' => 2, 'locale' => 'de_DE'],
    'Research outputs with German locale'
);

$testResults['research_outputs_locale_en'] = testEndpoint(
    $config,
    'research_outputs_locale_en',
    'research-outputs',
    ['size' => 2, 'locale' => 'en_GB'],
    'Research outputs with English locale'
);

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
echo "✅ Testing completed!\n";
echo "📊 Summary saved to: _summary.json\n";
echo str_repeat("=", 70) . "\n";
