<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use function array_filter;
use function array_unique;
use function array_values;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function mb_strtolower;
use function parse_url;
use function preg_match;
use function preg_split;
use function rawurlencode;
use function str_replace;
use function trim;
use function urldecode;

use const PHP_URL_HOST;
use const PHP_URL_PATH;

final class WikipediaService
{
    public const CACHE_TTL = 2592000;

    private ExternalAuthorityHttpClient $http_client;

    public function __construct(ExternalAuthorityHttpClient|null $http_client = null)
    {
        $this->http_client = $http_client ?? new ExternalAuthorityHttpClient();
    }

    /**
     * @param list<array{source:string,code:string,label:string,description:string,url:string,wikipedia_links:list<array{language:string,url:string}>}> $authority_rows
     *
     * @return list<array{language:string,url:string}>
     */
    public function linksFromAuthorityRows(array $authority_rows): array
    {
        $links = [];

        foreach ($authority_rows as $row) {
            foreach ($row['wikipedia_links'] as $link) {
                $validated = $this->validatedLink($link['language'], $link['url']);

                if ($validated !== null) {
                    $links[$validated['language']] = $validated;
                }
            }
        }

        ksort($links);

        return array_values($links);
    }

    /**
     * @return list<array{language:string,url:string}>
     */
    public function decodeLinks(string|null $json): array
    {
        $values = json_decode((string) $json, true);

        if (!is_array($values)) {
            return [];
        }

        $links = [];

        foreach ($values as $value) {
            if (!is_array($value)) {
                continue;
            }

            $validated = $this->validatedLink(
                (string) ($value['language'] ?? ''),
                (string) ($value['url'] ?? '')
            );

            if ($validated !== null) {
                $links[$validated['language']] = $validated;
            }
        }

        ksort($links);

        return array_values($links);
    }

    /**
     * @param array<string,mixed> $params
     *
     * @return list<array{language:string,url:string}>
     */
    public function linksFromPost(array $params): array
    {
        $languages = is_array($params['wikipediaLanguage'] ?? null) ? $params['wikipediaLanguage'] : [];
        $urls = is_array($params['wikipediaUrl'] ?? null) ? $params['wikipediaUrl'] : [];
        $links = [];

        foreach ($languages as $index => $language) {
            $validated = $this->validatedLink((string) $language, (string) ($urls[$index] ?? ''));

            if ($validated !== null) {
                $links[$validated['language']] = $validated;
            }
        }

        ksort($links);

        return array_values($links);
    }

    /**
     * @param list<array{language:string,url:string}> $links
     */
    public function encodeLinks(array $links): string
    {
        return (string) json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param list<array{language:string,url:string}> $links
     *
     * @return array{language:string,url:string,extract:string}|null
     */
    public function introduction(array $links, string $language_tag): array|null
    {
        $link = $this->preferredLink($links, $language_tag);

        if ($link === null) {
            return null;
        }

        $title = $this->titleFromUrl($link['url']);

        if ($title === '') {
            return null;
        }

        $api_url = 'https://' . $link['language'] . '.wikipedia.org/w/api.php?'
            . 'action=query&prop=extracts&exintro=1&explaintext=1&redirects=1'
            . '&format=json&formatversion=2&titles=' . rawurlencode($title);
        $data = $this->http_client->getJson('wikipedia-summary', $api_url, self::CACHE_TTL);
        $extract = $data['query']['pages'][0]['extract'] ?? null;

        if (!is_string($extract) || trim($extract) === '') {
            return null;
        }

        $paragraphs = preg_split('/\R\s*\R/u', trim($extract)) ?: [];
        $paragraphs = array_values(array_filter($paragraphs, static fn (string $paragraph): bool => trim($paragraph) !== ''));

        if ($paragraphs === []) {
            return null;
        }

        return [
            'language' => $link['language'],
            'url'      => $link['url'],
            'extract'  => trim($paragraphs[0]),
        ];
    }

    /**
     * @param list<array{language:string,url:string}> $links
     *
     * @return array{language:string,url:string}|null
     */
    private function preferredLink(array $links, string $language_tag): array|null
    {
        $primary_language = mb_strtolower(trim(explode('-', str_replace('_', '-', $language_tag))[0] ?? ''));

        foreach (array_values(array_unique([$primary_language, 'en'])) as $language) {
            foreach ($links as $link) {
                if ($link['language'] === $language) {
                    return $link;
                }
            }
        }

        return null;
    }

    /**
     * @return array{language:string,url:string}|null
     */
    private function validatedLink(string $language, string $url): array|null
    {
        $language = mb_strtolower(trim(str_replace('_', '-', $language)));
        $url = trim($url);

        if (preg_match('/^[a-z0-9-]{2,20}$/u', $language) !== 1 || $url === '') {
            return null;
        }

        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host !== $language . '.wikipedia.org') {
            return null;
        }

        if ($this->titleFromUrl($url) === '') {
            return null;
        }

        return [
            'language' => $language,
            'url'      => $url,
        ];
    }

    private function titleFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (preg_match('~^/wiki/(.+)$~u', $path, $match) !== 1) {
            return '';
        }

        return trim(str_replace('_', ' ', urldecode($match[1])));
    }
}
