<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\Enrichment;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\Uri;

class OpenAlexClient
{
    private const CACHE_TTL = 604800; // 7 days
    private const NEGATIVE_CACHE_TTL = 86400; // 24h

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    public function fetchByDoi(string $doi, ?string $mailto = null): array
    {
        $cacheKey = 'enrichment:openalex:' . sha1($doi);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $query = [
            'filter' => 'doi:https://doi.org/' . $doi,
        ];
        if (!empty($mailto)) {
            $query['mailto'] = $mailto;
        }

        $uri = (new Uri('https://api.openalex.org/works'))
            ->withQuery(http_build_query($query));

        try {
            $request = $this->requestFactory->createRequest('GET', $uri)
                ->withHeader('Accept', 'application/json')
                ->withHeader('User-Agent', 'univie_pure/12');

            $response = $this->client->sendRequest($request);
            $status = $response->getStatusCode();
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

            $result = is_array($data['results'][0] ?? null) ? $data['results'][0] : [];
            if ($result === []) {
                $payload = ['enabled' => true, 'ok' => false, 'notFound' => true];
                $this->cache->set($cacheKey, $payload, [], self::NEGATIVE_CACHE_TTL);
                return $payload;
            }

            $openAccess = is_array($result['open_access'] ?? null) ? $result['open_access'] : [];
            $payload = [
                'enabled' => true,
                'ok' => true,
                'citedByCount' => $result['cited_by_count'] ?? null,
                'isOa' => $openAccess['is_oa'] ?? null,
                'oaStatus' => $openAccess['oa_status'] ?? null,
                'institutions' => $this->extractInstitutionNames($result),
            ];

            $this->cache->set($cacheKey, $payload, [], self::CACHE_TTL);
            return $payload;
        } catch (\Throwable $exception) {
            $this->logger->warning('OpenAlex lookup failed', [
                'doi' => $doi,
                'exception' => $exception,
            ]);
            return [
                'enabled' => true,
                'ok' => false,
                'exception' => true,
            ];
        }
    }

    /**
     * @return string[]
     */
    private function extractInstitutionNames(array $result): array
    {
        $institutions = [];
        $authorships = is_array($result['authorships'] ?? null) ? $result['authorships'] : [];
        foreach ($authorships as $authorship) {
            if (!is_array($authorship)) {
                continue;
            }
            foreach ($authorship['institutions'] ?? [] as $institution) {
                if (!is_array($institution)) {
                    continue;
                }
                $name = trim((string)($institution['display_name'] ?? ''));
                if ($name !== '') {
                    $institutions[$name] = true;
                }
            }
        }

        return array_slice(array_keys($institutions), 0, 5);
    }
}

