<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use function array_values;
use function is_array;
use function json_decode;
use function time;

final class ExternalAuthorityHttpClient
{
    private ExternalAuthorityCacheService $cache;

    /** @var array<string,array{source:string,status:string,fetched_at:int|null}> */
    private array $request_statuses = [];

    public function __construct(ExternalAuthorityCacheService|null $cache = null)
    {
        $this->cache = $cache ?? new ExternalAuthorityCacheService();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getJson(string $source, string $url, int $ttl = ExternalAuthorityCacheService::DEFAULT_TTL): array|null
    {
        $cache_entry = $this->cache->entry($source, $url, $ttl);

        if ($cache_entry !== null && !$cache_entry['stale']) {
            $this->recordStatus($source, $url, 'current', $cache_entry['fetched_at']);

            return $cache_entry['data'];
        }

        try {
            $response = (new Client(['timeout' => 15]))->get($url);
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException) {
            return $this->staleFallback($source, $url, $cache_entry);
        }

        if (!is_array($data)) {
            return $this->staleFallback($source, $url, $cache_entry);
        }

        $this->cache->write($source, $url, $data);
        $this->recordStatus($source, $url, 'current', time());

        return $data;
    }

    /**
     * @return list<array{source:string,status:string,fetched_at:int|null}>
     */
    public function statusRows(): array
    {
        return array_values($this->request_statuses);
    }

    /**
     * @param array{data:array<string,mixed>,fetched_at:int,stale:bool}|null $cache_entry
     *
     * @return array<string,mixed>|null
     */
    private function staleFallback(string $source, string $url, array|null $cache_entry): array|null
    {
        if ($cache_entry !== null) {
            $this->recordStatus($source, $url, 'stale', $cache_entry['fetched_at']);

            return $cache_entry['data'];
        }

        $this->recordStatus($source, $url, 'unavailable', null);

        return null;
    }

    private function recordStatus(string $source, string $url, string $status, int|null $fetched_at): void
    {
        $this->request_statuses[$source . "\n" . $url] = [
            'source'     => $source,
            'status'     => $status,
            'fetched_at' => $fetched_at,
        ];
    }
}
