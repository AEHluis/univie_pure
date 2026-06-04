<?php
/**
 * Test CSL style download from GitHub with Proxy Support
 * Run from command line: php test_csl_download.php
 */

echo "=== CSL Download Test (with Proxy Support) ===\n\n";

// Load .env file for proxy configuration
$envFile = __DIR__ . '/.env';
$proxy = null;
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, 'PURE_PROXY=') === 0) {
            $proxy = trim(substr($line, 11));
            break;
        }
    }
}

// Also check environment variable
if (empty($proxy)) {
    $proxy = getenv('PURE_PROXY') ?: getenv('HTTP_PROXY') ?: getenv('HTTPS_PROXY');
}

echo "Proxy: " . ($proxy ?: 'none') . "\n\n";

// Test styles to download (updated - removed non-existent styles)
$styles = [
    'apa' => 'apa.csl',
    'ieee' => 'ieee.csl',
    'elsevier-vancouver' => 'elsevier-vancouver.csl',
    'din-1505-2' => 'din-1505-2.csl',
    'european-journal-of-theology' => 'european-journal-of-theology.csl',
    'chicago-notes-bibliography' => 'chicago-notes-bibliography.csl',
];

$baseUrl = 'https://raw.githubusercontent.com/citation-style-language/styles/master/';

// Test directory
$testDir = __DIR__ . '/test_csl_styles/';
if (!is_dir($testDir)) {
    mkdir($testDir, 0775, true);
    echo "Created test directory: $testDir\n\n";
}

$successCount = 0;
$failCount = 0;

foreach ($styles as $name => $filename) {
    $url = $baseUrl . $filename;
    echo "Testing: $name\n";
    echo "  URL: $url\n";

    $content = false;

    // Method 1: Try curl with proxy
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'TYPO3-UniviePure/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/xml, text/xml'],
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        // Add proxy if configured
        if (!empty($proxy)) {
            $curlOptions[CURLOPT_PROXY] = $proxy;
        }

        curl_setopt_array($ch, $curlOptions);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($content === false || $httpCode !== 200) {
            echo "  curl: FAILED (HTTP $httpCode)" . ($error ? " - $error" : "") . "\n";
            $content = false;
        } else {
            echo "  curl: OK (HTTP $httpCode)\n";
        }
    }

    // Method 2: Fallback to file_get_contents with proxy
    if ($content === false) {
        echo "  Trying file_get_contents...\n";

        $contextOptions = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: TYPO3-UniviePure/1.0',
                    'Accept: application/xml, text/xml',
                ],
                'timeout' => 30,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        // Add proxy if configured
        if (!empty($proxy)) {
            $contextOptions['http']['proxy'] = $proxy;
            $contextOptions['http']['request_fulluri'] = true;
        }

        $context = stream_context_create($contextOptions);
        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            echo "  file_get_contents: FAILED\n";
        } else {
            echo "  file_get_contents: OK\n";
        }
    }

    if ($content !== false && strlen($content) > 0) {
        // Validate CSL content
        $isValidCsl = str_contains($content, '<style') && str_contains($content, 'csl');
        echo "  Content length: " . strlen($content) . " bytes\n";
        echo "  Valid CSL XML: " . ($isValidCsl ? 'YES' : 'NO') . "\n";

        if ($isValidCsl) {
            // Save to test directory
            $savePath = $testDir . $filename;
            $saved = file_put_contents($savePath, $content);
            echo "  Saved to: $savePath (" . ($saved !== false ? 'OK' : 'FAILED') . ")\n";
            $successCount++;
        } else {
            echo "  NOT SAVED (invalid content)\n";
            $failCount++;
        }
    } else {
        $failCount++;
    }

    echo "\n";
}

// Summary
echo "=== Summary ===\n";
echo "Success: $successCount / " . count($styles) . "\n";
echo "Failed: $failCount / " . count($styles) . "\n\n";

// Check what was saved
echo "=== Files in test directory ===\n";
$files = glob($testDir . '*.csl');
foreach ($files as $file) {
    echo "  " . basename($file) . " (" . filesize($file) . " bytes)\n";
}

echo "\n=== Network diagnostics ===\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'enabled' : 'disabled') . "\n";
echo "curl extension: " . (function_exists('curl_init') ? 'loaded' : 'not loaded') . "\n";
echo "openssl extension: " . (extension_loaded('openssl') ? 'loaded' : 'not loaded') . "\n";

// Test raw connectivity (with proxy info)
echo "\nTesting raw connectivity to github.com...\n";
$socket = @fsockopen('raw.githubusercontent.com', 443, $errno, $errstr, 5);
if ($socket) {
    echo "  Direct connection: OK\n";
    fclose($socket);
} else {
    echo "  Direct connection: FAILED ($errno: $errstr)\n";
    if (!empty($proxy)) {
        echo "  Note: This is expected when using a proxy.\n";
    }
}

echo "\nDone.\n";
