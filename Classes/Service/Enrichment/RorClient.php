<?php

declare(strict_types=1);

namespace Univie\UniviePure\Service\Enrichment;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\Uri;

class RorClient
{
    private const CACHE_TTL = 1209600; // 14 days
    private const NEGATIVE_CACHE_TTL = 86400; // 24h

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param string[] $institutionNames
     * @return array<int, array<string, string>>
     */
    public function resolve(array $institutionNames): array
    {
        $results = [];
        foreach (array_slice($institutionNames, 0, 3) as $name) {
            $normalized = trim($name);
            if ($normalized === '') {
                continue;
            }
            $match = $this->resolveOne($normalized);
            if ($match !== null) {
                $results[] = $match;
            }
        }
        return $results;
    }

    /**
     * @return array<string, string>|null
     */
    private function resolveOne(string $name): ?array
    {
        $cacheKey = 'enrichment:ror:' . sha1(mb_strtolower($name));
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached === [] ? null : $cached;
        }

        $uri = (new Uri('https://api.ror.org/organizations'))
            ->withQuery(http_build_query(['query' => $name]));

        try {
            $request = $this->requestFactory->createRequest('GET', $uri)
                ->withHeader('Accept', 'application/json')
                ->withHeader('User-Agent', 'univie_pure/12');
            $response = $this->client->sendRequest($request);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                $this->cache->set($cacheKey, [], [], self::NEGATIVE_CACHE_TTL);
                return null;
            }

            $data = json_decode((string)$response->getBody(), true);
            if (!is_array($data)) {
                $this->cache->set($cacheKey, [], [], self::NEGATIVE_CACHE_TTL);
                return null;
            }

            $first = is_array($data['items'][0] ?? null) ? $data['items'][0] : [];
            $organization = is_array($first['organization'] ?? null) ? $first['organization'] : $first;
            $id = trim((string)($organization['id'] ?? ''));
            $matchedName = trim((string)($organization['name'] ?? ''));

            if ($id === '' || $matchedName === '') {
                $this->cache->set($cacheKey, [], [], self::NEGATIVE_CACHE_TTL);
                return null;
            }

            $match = [
                'query' => $name,
                'name' => $matchedName,
                'id' => $id,
            ];
            $this->cache->set($cacheKey, $match, [], self::CACHE_TTL);
            return $match;
        } catch (\Throwable $exception) {
            $this->logger->warning('ROR lookup failed', [
                'institution' => $name,
                'exception' => $exception,
            ]);
            return null;
        }
    }
}

