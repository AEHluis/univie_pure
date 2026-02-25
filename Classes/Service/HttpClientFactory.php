<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service;

use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface;
use TYPO3\CMS\Core\Core\Environment;
use Univie\UniviePure\Utility\DotEnv;

/**
 * Factory to create HTTP clients with proxy configuration from .env
 */
class HttpClientFactory
{
    private static ?string $proxyUrl = null;

    public static function create(): ClientInterface
    {
        $config = [
            'verify' => true,
        ];

        $proxy = self::getProxyUrl();
        if (!empty($proxy)) {
            $config['proxy'] = $proxy;
        }

        return new Client($config);
    }

    private static function getProxyUrl(): string
    {
        if (self::$proxyUrl !== null) {
            return self::$proxyUrl;
        }

        // Try environment variable first
        $proxy = getenv('PURE_PROXY');
        if ($proxy !== false && !empty($proxy)) {
            self::$proxyUrl = $proxy;
            return self::$proxyUrl;
        }

        // Fall back to .env file
        try {
            $envPath = Environment::getProjectPath() . '/.env';
            if (file_exists($envPath)) {
                $dotEnv = new DotEnv($envPath);
                $dotEnv->load();
                self::$proxyUrl = $dotEnv->variables['PURE_PROXY'] ?? '';
            } else {
                self::$proxyUrl = '';
            }
        } catch (\Exception $e) {
            self::$proxyUrl = '';
        }

        return self::$proxyUrl;
    }
}
