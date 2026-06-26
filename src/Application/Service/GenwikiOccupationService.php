<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use Hartenthaler\Webtrees\Module\OccupationStandardizer\Infrastructure\Persistence\Schema\OccupationSchema;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use SimpleXMLElement;
use ZipArchive;

use function array_chunk;
use function array_column;
use function array_filter;
use function array_unique;
use function array_values;
use function class_exists;
use function count;
use function implode;
use function is_file;
use function mb_strtolower;
use function ord;
use function preg_match;
use function preg_replace;
use function sha1_file;
use function simplexml_load_string;
use function str_starts_with;
use function strlen;
use function trim;

final class GenwikiOccupationService
{
    private const METADATA_HASH = 'genwiki_occupation_hash';

    /**
     * @return array{imported:bool,row_count:int}
     */
    public function ensureImported(string $file): array
    {
        if (!$this->hasTables() || !class_exists(ZipArchive::class) || !is_file($file)) {
            return ['imported' => false, 'row_count' => 0];
        }

        $hash = (string) sha1_file($file);
        $stored_hash = (string) (DB::table(OccupationSchema::TABLE_METADATA)
            ->where('setting_name', '=', self::METADATA_HASH)
            ->value('setting_value') ?? '');

        if ($stored_hash === $hash && DB::table(OccupationSchema::TABLE_GENWIKI_OCCUPATIONS)->exists()) {
            return ['imported' => false, 'row_count' => 0];
        }

        $records = [];

        foreach ($this->xlsxRows($file) as $row) {
            $occupation_text = trim((string) ($row['Beruf'] ?? ''));
            $genwiki_url = trim((string) ($row['Link'] ?? ''));

            if ($occupation_text === '' || !str_starts_with($genwiki_url, 'https://wiki.genealogy.net/')) {
                continue;
            }

            $records[$this->matchKey($occupation_text)] = [
                'occupation_text' => $occupation_text,
                'genwiki_url'     => $genwiki_url,
            ];
        }

        if ($records === []) {
            return ['imported' => false, 'row_count' => 0];
        }

        foreach (array_chunk(array_values($records), 200) as $chunk) {
            DB::table(OccupationSchema::TABLE_GENWIKI_OCCUPATIONS)->upsert(
                $chunk,
                ['occupation_text'],
                ['genwiki_url']
            );
        }

        DB::table(OccupationSchema::TABLE_GENWIKI_OCCUPATIONS)
            ->whereNotIn('occupation_text', array_column($records, 'occupation_text'))
            ->delete();

        $this->storeHash($hash);

        return ['imported' => true, 'row_count' => count($records)];
    }

    /**
     * @param array{preferred_label:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string} $concept
     *
     * @return list<array{text:string,url:string}>
     */
    public function linksForConcept(array $concept): array
    {
        if (!$this->hasTables()) {
            return [];
        }

        $candidates = array_values(array_filter(array_unique([
            trim($concept['preferred_label']),
            trim($concept['occupation_de_male']),
            trim($concept['occupation_de_female']),
            trim($concept['occupation_de_neutral']),
        ]), static fn (string $value): bool => $value !== ''));

        if ($candidates === []) {
            return [];
        }

        return DB::table(OccupationSchema::TABLE_GENWIKI_OCCUPATIONS)
            ->whereIn('occupation_text', $candidates)
            ->orderBy('occupation_text')
            ->get(['occupation_text', 'genwiki_url'])
            ->map(static fn (object $row): array => [
                'text' => (string) $row->occupation_text,
                'url'  => (string) $row->genwiki_url,
            ])
            ->values()
            ->all();
    }

    private function hasTables(): bool
    {
        return DB::schema()->hasTable(OccupationSchema::TABLE_METADATA)
            && DB::schema()->hasTable(OccupationSchema::TABLE_GENWIKI_OCCUPATIONS);
    }

    private function storeHash(string $hash): void
    {
        $metadata = DB::table(OccupationSchema::TABLE_METADATA);

        if ($metadata->where('setting_name', '=', self::METADATA_HASH)->exists()) {
            DB::table(OccupationSchema::TABLE_METADATA)
                ->where('setting_name', '=', self::METADATA_HASH)
                ->update(['setting_value' => $hash]);

            return;
        }

        try {
            DB::table(OccupationSchema::TABLE_METADATA)->insert([
                'setting_name'  => self::METADATA_HASH,
                'setting_value' => $hash,
            ]);
        } catch (QueryException $ex) {
            if (($ex->errorInfo[1] ?? null) !== 1062) {
                throw $ex;
            }

            DB::table(OccupationSchema::TABLE_METADATA)
                ->where('setting_name', '=', self::METADATA_HASH)
                ->update(['setting_value' => $hash]);
        }
    }

    /**
     * @return list<array<string,string>>
     */
    private function xlsxRows(string $file): array
    {
        $zip = new ZipArchive();

        if ($zip->open($file) !== true) {
            return [];
        }

        $shared_strings = $this->sharedStrings($zip);
        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheet_xml === false) {
            return [];
        }

        $xml = simplexml_load_string($sheet_xml);

        if (!$xml instanceof SimpleXMLElement) {
            return [];
        }

        $rows = [];
        $headers = [];

        foreach ($xml->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $values[$this->columnIndex($reference)] = $this->cellValue($cell, $shared_strings);
            }

            if ($headers === []) {
                $headers = $values;
                continue;
            }

            $record = [];

            foreach ($headers as $column => $header) {
                $header = trim((string) $header);

                if ($header !== '') {
                    $record[$header] = trim((string) ($values[$column] ?? ''));
                }
            }

            if ($record !== []) {
                $rows[] = $record;
            }
        }

        return $rows;
    }

    /**
     * @return array<int,string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml_string = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml_string === false) {
            return [];
        }

        $xml = simplexml_load_string($xml_string);

        if (!$xml instanceof SimpleXMLElement) {
            return [];
        }

        $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($xml->si as $si) {
            $text_parts = [];

            foreach ($si->xpath('.//m:t') ?: [] as $text) {
                $text_parts[] = (string) $text;
            }

            $strings[] = implode('', $text_parts);
        }

        return $strings;
    }

    /**
     * @param array<int,string> $shared_strings
     */
    private function cellValue(SimpleXMLElement $cell, array $shared_strings): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            return $shared_strings[(int) $cell->v] ?? '';
        }

        if ($type === 'inlineStr') {
            $cell->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $text = $cell->xpath('.//m:t');

            return isset($text[0]) ? (string) $text[0] : '';
        }

        return (string) ($cell->v ?? '');
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/u', $reference, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    private function matchKey(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
    }
}
