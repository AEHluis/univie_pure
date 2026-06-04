<?php
/**
 * Test script to verify CSL locale setup
 */

// Set proxy from .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

echo "=== CSL Locale Setup Test ===\n\n";

// 1. Check if locales directory can be created
$localesDir = __DIR__ . '/Libraries/citeproc-php/vendor/citation-style-language/locales';
echo "Target locales directory: $localesDir\n";

if (!is_dir($localesDir)) {
    echo "Creating directory...\n";
    if (mkdir($localesDir, 0755, true)) {
        echo "✓ Directory created successfully\n";
    } else {
        echo "✗ Failed to create directory\n";
        exit(1);
    }
} else {
    echo "✓ Directory already exists\n";
}

// 2. Test downloading locales.json
echo "\n--- Testing locales.json download ---\n";
$localesJsonUrl = 'https://raw.githubusercontent.com/citation-style-language/locales/master/locales.json';
$localesJsonPath = $localesDir . '/locales.json';

if (!file_exists($localesJsonPath)) {
    echo "Downloading from: $localesJsonUrl\n";

    // Setup context with proxy if needed
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'TYPO3 univie_pure Test',
        ],
    ]);

    $proxy = getenv('PURE_PROXY');
    if ($proxy) {
        echo "Using proxy: $proxy\n";
        stream_context_set_option($context, 'http', 'proxy', $proxy);
        stream_context_set_option($context, 'http', 'request_fulluri', true);
    }

    $content = @file_get_contents($localesJsonUrl, false, $context);

    if ($content === false) {
        echo "✗ Failed to download locales.json\n";
        $error = error_get_last();
        if ($error) {
            echo "Error: {$error['message']}\n";
        }
    } else {
        file_put_contents($localesJsonPath, $content);
        echo "✓ Downloaded locales.json (" . strlen($content) . " bytes)\n";
    }
} else {
    echo "✓ locales.json already exists\n";
}

// 3. Test downloading required locale files
$requiredLocales = ['de-DE', 'en-GB', 'en-US'];
echo "\n--- Testing locale file downloads ---\n";

foreach ($requiredLocales as $locale) {
    $localeFile = $localesDir . '/locales-' . $locale . '.xml';

    if (file_exists($localeFile)) {
        echo "✓ $locale already exists\n";
        continue;
    }

    $url = 'https://raw.githubusercontent.com/citation-style-language/locales/master/locales-' . $locale . '.xml';
    echo "Downloading $locale from: $url\n";

    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'TYPO3 univie_pure Test',
        ],
    ]);

    $proxy = getenv('PURE_PROXY');
    if ($proxy) {
        stream_context_set_option($context, 'http', 'proxy', $proxy);
        stream_context_set_option($context, 'http', 'request_fulluri', true);
    }

    $content = @file_get_contents($url, false, $context);

    if ($content === false) {
        echo "✗ Failed to download $locale\n";
    } else {
        file_put_contents($localeFile, $content);
        echo "✓ Downloaded $locale (" . strlen($content) . " bytes)\n";
    }
}

// 4. Verify all files exist
echo "\n--- Verification ---\n";
$allGood = true;

if (!file_exists($localesJsonPath)) {
    echo "✗ Missing: locales.json\n";
    $allGood = false;
} else {
    echo "✓ Found: locales.json\n";
}

foreach ($requiredLocales as $locale) {
    $localeFile = $localesDir . '/locales-' . $locale . '.xml';
    if (!file_exists($localeFile)) {
        echo "✗ Missing: locales-$locale.xml\n";
        $allGood = false;
    } else {
        echo "✓ Found: locales-$locale.xml\n";
    }
}

echo "\n";
if ($allGood) {
    echo "=== ✓ All CSL locales are ready! ===\n";
} else {
    echo "=== ✗ Some locales are missing ===\n";
    exit(1);
}
