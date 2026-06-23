<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use function array_filter;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function is_array;
use function is_string;
use function rawurlencode;
use function str_replace;
use function trim;

final class ExternalOccupationAuthorityService
{
    private ExternalAuthorityHttpClient $http_client;

    public function __construct(ExternalAuthorityHttpClient|null $http_client = null)
    {
        $this->http_client = $http_client ?? new ExternalAuthorityHttpClient();
    }

    /**
     * @param array<string,list<string>> $identifiers
     *
     * @return list<array{source:string,label:string,description:string,url:string,wikipedia_url:string}>
     */
    public function rowsForIdentifiers(array $identifiers, string $language_tag): array
    {
        $rows = [];

        foreach ($identifiers['wikidata'] ?? [] as $id) {
            $rows[] = $this->wikidataRow($id, $language_tag);
        }

        foreach ($identifiers['factgrid'] ?? [] as $id) {
            $rows[] = $this->factgridRow($id, $language_tag);
        }

        foreach ($identifiers['gnd'] ?? [] as $id) {
            $rows[] = $this->gndRow($id);
        }

        return array_values(array_filter($rows));
    }

    /**
     * @return array{source:string,label:string,description:string,url:string,wikipedia_url:string}|null
     */
    private function wikidataRow(string $id, string $language_tag): array|null
    {
        if ($id === '') {
            return null;
        }

        $url = 'https://www.wikidata.org/wiki/Special:EntityData/' . rawurlencode($id) . '.json';
        $data = $this->http_client->getJson('wikidata', $url);
        $entity = $this->entity($data, $id);

        if ($entity === null) {
            return [
                'source'        => 'Wikidata',
                'label'         => '',
                'description'   => '',
                'url'           => 'https://www.wikidata.org/wiki/' . rawurlencode($id),
                'wikipedia_url' => '',
            ];
        }

        return [
            'source'        => 'Wikidata',
            'label'         => $this->languageValue($entity['labels'] ?? [], $language_tag),
            'description'   => $this->languageValue($entity['descriptions'] ?? [], $language_tag),
            'url'           => 'https://www.wikidata.org/wiki/' . rawurlencode($id),
            'wikipedia_url' => $this->wikipediaUrl($entity, $language_tag),
        ];
    }

    /**
     * @return array{source:string,label:string,description:string,url:string,wikipedia_url:string}|null
     */
    private function factgridRow(string $id, string $language_tag): array|null
    {
        if ($id === '') {
            return null;
        }

        $url = 'https://database.factgrid.de/wiki/Special:EntityData/' . rawurlencode($id) . '.json';
        $data = $this->http_client->getJson('factgrid', $url);
        $entity = $this->entity($data, $id);

        if ($entity === null) {
            return [
                'source'        => 'FactGrid',
                'label'         => '',
                'description'   => '',
                'url'           => 'https://database.factgrid.de/wiki/Item:' . rawurlencode($id),
                'wikipedia_url' => '',
            ];
        }

        return [
            'source'        => 'FactGrid',
            'label'         => $this->languageValue($entity['labels'] ?? [], $language_tag),
            'description'   => $this->languageValue($entity['descriptions'] ?? [], $language_tag),
            'url'           => 'https://database.factgrid.de/wiki/Item:' . rawurlencode($id),
            'wikipedia_url' => '',
        ];
    }

    /**
     * @return array{source:string,label:string,description:string,url:string,wikipedia_url:string}|null
     */
    private function gndRow(string $id): array|null
    {
        if ($id === '') {
            return null;
        }

        $url = 'https://lobid.org/gnd/' . rawurlencode($id) . '.json';
        $data = $this->http_client->getJson('gnd', $url);

        if ($data === null) {
            return [
                'source'        => 'GND',
                'label'         => '',
                'description'   => '',
                'url'           => 'https://d-nb.info/gnd/' . rawurlencode($id),
                'wikipedia_url' => '',
            ];
        }

        return [
            'source'        => 'GND',
            'label'         => (string) ($data['preferredName'] ?? ''),
            'description'   => $this->gndDescription($data),
            'url'           => 'https://d-nb.info/gnd/' . rawurlencode($id),
            'wikipedia_url' => '',
        ];
    }

    /**
     * @param array<string,mixed>|null $data
     *
     * @return array<string,mixed>|null
     */
    private function entity(array|null $data, string $id): array|null
    {
        $entity = $data['entities'][$id] ?? null;

        return is_array($entity) ? $entity : null;
    }

    /**
     * @param mixed $values
     */
    private function languageValue(mixed $values, string $language_tag): string
    {
        if (!is_array($values)) {
            return '';
        }

        foreach ($this->languageCandidates($language_tag) as $language) {
            $value = $values[$language]['value'] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param array<string,mixed> $entity
     */
    private function wikipediaUrl(array $entity, string $language_tag): string
    {
        $sitelinks = $entity['sitelinks'] ?? [];

        if (!is_array($sitelinks)) {
            return '';
        }

        foreach ($this->languageCandidates($language_tag) as $language) {
            $title = $sitelinks[$language . 'wiki']['title'] ?? null;

            if (is_string($title) && trim($title) !== '') {
                return 'https://' . $language . '.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', trim($title)));
            }
        }

        return '';
    }

    /**
     * @param array<string,mixed> $data
     */
    private function gndDescription(array $data): string
    {
        $parts = [];

        foreach (['definition', 'professionOrOccupation', 'broaderTermInstantial', 'broaderTermGeneric'] as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_array($item) && is_string($item['label'] ?? null) && trim($item['label']) !== '') {
                        $parts[] = trim($item['label']);
                    } elseif (is_string($item) && trim($item) !== '') {
                        $parts[] = trim($item);
                    }
                }
            }
        }

        return implode('; ', array_values(array_unique($parts)));
    }

    /**
     * @return list<string>
     */
    private function languageCandidates(string $language_tag): array
    {
        $primary_language = trim(explode('-', str_replace('_', '-', $language_tag))[0] ?? '');
        $candidates = [$primary_language, 'de', 'en'];

        return array_values(array_filter(array_unique($candidates), static fn (string $language): bool => $language !== ''));
    }
}
