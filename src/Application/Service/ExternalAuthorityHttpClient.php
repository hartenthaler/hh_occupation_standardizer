<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use function is_array;
use function json_decode;

final class ExternalAuthorityHttpClient
{
    private ExternalAuthorityCacheService $cache;

    public function __construct(ExternalAuthorityCacheService|null $cache = null)
    {
        $this->cache = $cache ?? new ExternalAuthorityCacheService();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getJson(string $source, string $url, int $ttl = ExternalAuthorityCacheService::DEFAULT_TTL): array|null
    {
        $cached = $this->cache->read($source, $url, $ttl);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = (new Client(['timeout' => 15]))->get($url);
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $this->cache->write($source, $url, $data);

        return $data;
    }
}
