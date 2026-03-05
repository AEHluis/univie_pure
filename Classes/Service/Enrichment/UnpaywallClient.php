<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\Enrichment;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\Uri;

class UnpaywallClient
{
    private const CACHE_TTL = 2592000; // 30 days
    private const NEGATIVE_CACHE_TTL = 86400; // 24h

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    public function fetchByDoi(string $doi, string $email, int $timeoutSeconds = 4): array
    {
        $email = trim($email);
        if ($email === '') {
            return [
                'enabled' => false,
                'ok' => false,
            ];
        }

        $cacheKey = 'enrichment:unpaywall:' . sha1($doi);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $uri = (new Uri('https://api.unpaywall.org/v2/' . rawurlencode($doi)))
            ->withQuery(http_build_query(['email' => $email]));

        try {
            $request = $this->requestFactory->createRequest('GET', $uri)
                ->withHeader('Accept', 'application/json')
                ->withHeader('User-Agent', 'univie_pure/12');

            $response = $this->client->sendRequest($request);
            $status = $response->getStatusCode();

            if ($status === 404) {
                $payload = ['enabled' => true, 'ok' => false, 'notFound' => true];
                $this->cache->set($cacheKey, $payload, [], self::NEGATIVE_CACHE_TTL);
                return $payload;
            }

            if ($status < 200 || $status >= 300) {
                $payload = ['enabled' => true, 'ok' => false, 'status' => $status];
                $this->cache->set($cacheKey, $payload, [], self::NEGATIVE_CACHE_TTL);
                return $payload;
            }

            $data = json_decode((string)$response->getBody(), true);
            if (!is_array($data)) {
                $payload = ['enabled' => true, 'ok' => false, 'parseError' => true];
                $this->cache->set($cacheKey, $payload, [], self::NEGATIVE_CACHE_TTL);
                return $payload;
            }

            $bestLocation = is_array($data['best_oa_location'] ?? null) ? $data['best_oa_location'] : [];
            $payload = [
                'enabled' => true,
                'ok' => true,
                'isOa' => $data['is_oa'] ?? null,
                'oaStatus' => $data['oa_status'] ?? null,
                'bestPdfUrl' => $bestLocation['url_for_pdf'] ?? null,
                'bestUrl' => $bestLocation['url'] ?? ($bestLocation['url_for_landing_page'] ?? null),
                'version' => $bestLocation['version'] ?? null,
                'license' => $bestLocation['license'] ?? null,
                'updated' => $data['updated'] ?? null,
            ];

            $this->cache->set($cacheKey, $payload, [], self::CACHE_TTL);
            return $payload;
        } catch (\Throwable $exception) {
            $this->logger->warning('Unpaywall lookup failed', [
                'doi' => $doi,
                'exception' => $exception,
                'timeout' => $timeoutSeconds,
            ]);
            return [
                'enabled' => true,
                'ok' => false,
                'exception' => true,
            ];
        }
    }
}

