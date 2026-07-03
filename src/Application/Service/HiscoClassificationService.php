<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use Hartenthaler\Webtrees\Module\OccupationStandardizer\Infrastructure\Persistence\Schema\OccupationSchema;
use Illuminate\Database\Capsule\Manager as DB;
use SimpleXMLElement;
use ZipArchive;

use function array_chunk;
use function array_keys;
use function array_values;
use function class_exists;
use function count;
use function hash;
use function implode;
use function is_file;
use function ord;
use function preg_match;
use function preg_replace;
use function sha1_file;
use function simplexml_load_string;
use function strlen;
use function trim;

final class HiscoClassificationService
{
    private const METADATA_HASH = 'hisco_classifications_hash';
    private const HISCAM_FILE = 'hisco_hiscam_occ1950.xlsx';
    private const HISCLASS_FILE = 'hisco_hisclass.xlsx';

    /**
     * @return array{imported:bool,row_count:int}
     */
    public function ensureImported(string $data_directory): array
    {
        if (!DB::schema()->hasTable(OccupationSchema::TABLE_HISCO_CLASSIFICATIONS) || !class_exists(ZipArchive::class)) {
            return ['imported' => false, 'row_count' => 0];
        }

        $hiscam_file = $data_directory . self::HISCAM_FILE;
        $hisclass_file = $data_directory . self::HISCLASS_FILE;

        if (!is_file($hiscam_file) || !is_file($hisclass_file)) {
            return ['imported' => false, 'row_count' => 0];
        }

        $hash = hash('sha1', (string) sha1_file($hiscam_file) . '|' . (string) sha1_file($hisclass_file));
        $stored_hash = (string) (DB::table(OccupationSchema::TABLE_METADATA)
            ->where('setting_name', '=', self::METADATA_HASH)
            ->value('setting_value') ?? '');

        if ($stored_hash === $hash && DB::table(OccupationSchema::TABLE_HISCO_CLASSIFICATIONS)->exists()) {
            return ['imported' => false, 'row_count' => 0];
        }

        $rows = [];

        foreach ($this->xlsxRows($hiscam_file) as $row) {
            $hisco_id = $this->positiveInteger($row['HISCO'] ?? '');

            if ($hisco_id === null) {
                continue;
            }

            $rows[$hisco_id] = [
                'hisco_id'   => $hisco_id,
                'hiscam_u1'  => $this->optionalScore($row['HISCAM_U1'] ?? ''),
                'hiscam_nl'  => $this->optionalScore($row['HISCAM_NL'] ?? ''),
                'occ1950'    => $this->optionalInteger($row['OCC1950'] ?? ''),
                'hisclass'   => null,
                'hisclass_5' => null,
            ];
        }

        foreach ($this->xlsxRows($hisclass_file) as $row) {
            $hisco_id = $this->positiveInteger($row['HISCO'] ?? '');

            if ($hisco_id === null) {
                continue;
            }

            $rows[$hisco_id] ??= [
                'hisco_id'   => $hisco_id,
                'hiscam_u1'  => null,
                'hiscam_nl'  => null,
                'occ1950'    => null,
                'hisclass'   => null,
                'hisclass_5' => null,
            ];
            $rows[$hisco_id]['hisclass'] = $this->optionalInteger($row['HISCLASS'] ?? '');
            $rows[$hisco_id]['hisclass_5'] = $this->optionalInteger($row['HISCLASS_5'] ?? '');
        }

        if ($rows === []) {
            return ['imported' => false, 'row_count' => 0];
        }

        foreach (array_chunk(array_values($rows), 250) as $chunk) {
            DB::table(OccupationSchema::TABLE_HISCO_CLASSIFICATIONS)->upsert(
                $chunk,
                ['hisco_id'],
                ['hiscam_u1', 'hiscam_nl', 'occ1950', 'hisclass', 'hisclass_5']
            );
        }

        DB::table(OccupationSchema::TABLE_HISCO_CLASSIFICATIONS)
            ->whereNotIn('hisco_id', array_keys($rows))
            ->delete();

        $updated = DB::table(OccupationSchema::TABLE_METADATA)
            ->where('setting_name', '=', self::METADATA_HASH)
            ->update(['setting_value' => $hash]);

        if ($updated === 0) {
            DB::table(OccupationSchema::TABLE_METADATA)->insertOrIgnore([
                'setting_name'  => self::METADATA_HASH,
                'setting_value' => $hash,
            ]);
        }

        return ['imported' => true, 'row_count' => count($rows)];
    }

    /**
     * @return array{hiscam_u1:float|null,hiscam_nl:float|null,occ1950:int|null,hisclass:int|null,hisclass_5:int|null}|null
     */
    public function classification(string $code): array|null
    {
        if (!DB::schema()->hasTable(OccupationSchema::TABLE_HISCO_CLASSIFICATIONS)) {
            return null;
        }

        $hisco_id = $this->positiveInteger((string) preg_replace('/[^0-9]/u', '', $code));

        if ($hisco_id === null) {
            return null;
        }

        $row = DB::table(OccupationSchema::TABLE_HISCO_CLASSIFICATIONS)
            ->where('hisco_id', '=', $hisco_id)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'hiscam_u1'  => $row->hiscam_u1 !== null ? (float) $row->hiscam_u1 : null,
            'hiscam_nl'  => $row->hiscam_nl !== null ? (float) $row->hiscam_nl : null,
            'occ1950'    => $row->occ1950 !== null ? (int) $row->occ1950 : null,
            'hisclass'   => $row->hisclass !== null ? (int) $row->hisclass : null,
            'hisclass_5' => $row->hisclass_5 !== null ? (int) $row->hisclass_5 : null,
        ];
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

        $strings = [];

        foreach ($xml->si as $si) {
            $text_parts = [];

            foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $text) {
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
            $text = $cell->xpath('.//*[local-name()="t"]');

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

    private function positiveInteger(string $value): int|null
    {
        $integer = (int) trim($value);

        return $integer > 0 ? $integer : null;
    }

    private function optionalInteger(string $value): int|null
    {
        $value = trim($value);

        if ($value === '' || $value === '-9') {
            return null;
        }

        return (int) $value;
    }

    private function optionalScore(string $value): float|null
    {
        $value = trim($value);

        if ($value === '' || $value === '-9') {
            return null;
        }

        return (float) $value;
    }
}
