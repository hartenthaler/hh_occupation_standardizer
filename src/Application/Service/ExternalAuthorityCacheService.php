<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use Fisharebest\Webtrees\Webtrees;

use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function is_dir;
use function json_decode;
use function json_encode;
use function md5;
use function mkdir;
use function preg_replace;
use function time;

final class ExternalAuthorityCacheService
{
    public const DEFAULT_TTL = 86400;

    /**
     * @return array<string,mixed>|null
     */
    public function read(string $source, string $cache_key, int $ttl = self::DEFAULT_TTL): array|null
    {
        $entry = $this->entry($source, $cache_key, $ttl);

        return $entry !== null && !$entry['stale'] ? $entry['data'] : null;
    }

    /**
     * @return array{data:array<string,mixed>,fetched_at:int,stale:bool}|null
     */
    public function entry(string $source, string $cache_key, int $ttl = self::DEFAULT_TTL): array|null
    {
        $cache_file = $this->cacheFile($source, $cache_key);
        $fetched_at = file_exists($cache_file) ? filemtime($cache_file) : false;

        if ($fetched_at === false) {
            return null;
        }

        $contents = file_get_contents($cache_file);

        if ($contents === false || $contents === '') {
            return null;
        }

        $data = json_decode($contents, true);

        if (!is_array($data)) {
            return null;
        }

        return [
            'data'       => $data,
            'fetched_at' => $fetched_at,
            'stale'      => time() - $fetched_at >= $ttl,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function write(string $source, string $cache_key, array $data): void
    {
        $cache_file = $this->cacheFile($source, $cache_key);
        $cache_directory = dirname($cache_file);

        if (!is_dir($cache_directory)) {
            @mkdir($cache_directory, 0775, true);
        }

        $contents = json_encode($data);

        if ($contents !== false) {
            @file_put_contents($cache_file, $contents);
        }
    }

    private function cacheFile(string $source, string $cache_key): string
    {
        return Webtrees::DATA_DIR
            . 'cache/hh_occupation_standardizer/'
            . $this->safeSource($source)
            . '-'
            . md5($cache_key)
            . '.json';
    }

    private function safeSource(string $source): string
    {
        return preg_replace('/[^a-z0-9_-]+/i', '-', $source) ?: 'external';
    }
}
