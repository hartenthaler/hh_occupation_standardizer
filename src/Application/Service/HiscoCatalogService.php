<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use Hartenthaler\Webtrees\Module\OccupationStandardizer\Infrastructure\Persistence\Schema\OccupationSchema;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;

use function array_filter;
use function array_map;
use function array_values;
use function date;
use function fclose;
use function fgetcsv;
use function fopen;
use function hash;
use function implode;
use function is_file;
use function preg_replace;
use function sha1_file;
use function str_starts_with;
use function trim;

final class HiscoCatalogService
{
    private const METADATA_HASH = 'hisco_catalog_hash';
    private const TRANSLATION_FILE = 'hisco_hierarchy_de.csv';

    /**
     * @return array{imported:bool,row_count:int}
     */
    public function ensureImported(string $data_directory): array
    {
        if (!$this->hasTables()) {
            return ['imported' => false, 'row_count' => 0];
        }

        $files = [
            'major'      => $data_directory . 'hisco_major_group.csv',
            'minor'      => $data_directory . 'hisco_minor_group.csv',
            'unit'       => $data_directory . 'hisco_unit_group.csv',
            'occupation' => $data_directory . 'hisco_occupation.csv',
        ];
        $translation_file = $data_directory . self::TRANSLATION_FILE;

        foreach ($files as $file) {
            if (!is_file($file)) {
                return ['imported' => false, 'row_count' => 0];
            }
        }

        $hash = $this->catalogHash($files, is_file($translation_file) ? $translation_file : null);
        $stored_hash = (string) (DB::table(OccupationSchema::TABLE_METADATA)
            ->where('setting_name', '=', self::METADATA_HASH)
            ->value('setting_value') ?? '');

        if ($stored_hash === $hash && DB::table(OccupationSchema::TABLE_HISCO_OCCUPATIONS)->exists()) {
            return ['imported' => false, 'row_count' => 0];
        }

        $row_count = 0;
        $row_count += $this->importMajorGroups($files['major']);
        $row_count += $this->importMinorGroups($files['minor']);
        $row_count += $this->importUnitGroups($files['unit']);
        $row_count += $this->importOccupations($files['occupation']);

        if (is_file($translation_file)) {
            $row_count += $this->importGermanHierarchyTranslations($translation_file);
        }

        $this->safeUpdateOrInsert(
            OccupationSchema::TABLE_METADATA,
            ['setting_name' => self::METADATA_HASH],
            ['setting_value' => $hash]
        );

        return ['imported' => true, 'row_count' => $row_count];
    }

    /**
     * @return array{hisco_id:string,hisco_pretty:string,label:string,description:string,unit:array{code:string,label:string,description:string},minor:array{code:string,label:string,description:string},major:array{code:string,label:string,description:string}}|null
     */
    public function occupation(string $code, string $language_tag): array|null
    {
        if (!$this->hasTables()) {
            return null;
        }

        $hisco_id = $this->normalizedCode($code);

        if ($hisco_id === '') {
            return null;
        }

        $row = DB::table(OccupationSchema::TABLE_HISCO_OCCUPATIONS . ' AS occupations')
            ->join(OccupationSchema::TABLE_HISCO_UNIT_GROUPS . ' AS units', 'units.unit_id', '=', 'occupations.unit_id')
            ->join(OccupationSchema::TABLE_HISCO_MINOR_GROUPS . ' AS minors', 'minors.minor_id', '=', 'units.minor_id')
            ->join(OccupationSchema::TABLE_HISCO_MAJOR_GROUPS . ' AS majors', 'majors.major_id', '=', 'minors.major_id')
            ->where('occupations.hisco_id', '=', (int) $hisco_id)
            ->select([
                'occupations.hisco_id',
                'occupations.hisco_pretty',
                'occupations.label_en AS occupation_label_en',
                'occupations.description_en AS occupation_description_en',
                'units.unit_id',
                'units.label_en AS unit_label_en',
                'units.label_de AS unit_label_de',
                'units.description_en AS unit_description_en',
                'units.description_de AS unit_description_de',
                'minors.minor_id',
                'minors.label_en AS minor_label_en',
                'minors.label_de AS minor_label_de',
                'minors.description_en AS minor_description_en',
                'minors.description_de AS minor_description_de',
                'majors.major_id',
                'majors.label_en AS major_label_en',
                'majors.label_de AS major_label_de',
                'majors.description_en AS major_description_en',
                'majors.description_de AS major_description_de',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        $use_german = $this->useGerman($language_tag);

        return [
            'hisco_id'     => (string) $row->hisco_id,
            'hisco_pretty' => (string) $row->hisco_pretty,
            'label'        => (string) $row->occupation_label_en,
            'description'  => (string) $row->occupation_description_en,
            'unit'         => [
                'code'        => (string) $row->unit_id,
                'label'       => $use_german && (string) ($row->unit_label_de ?? '') !== '' ? (string) $row->unit_label_de : (string) $row->unit_label_en,
                'description' => $use_german && (string) ($row->unit_description_de ?? '') !== '' ? (string) $row->unit_description_de : (string) $row->unit_description_en,
            ],
            'minor'        => [
                'code'        => (string) $row->minor_id,
                'label'       => $use_german && (string) ($row->minor_label_de ?? '') !== '' ? (string) $row->minor_label_de : (string) $row->minor_label_en,
                'description' => $use_german && (string) ($row->minor_description_de ?? '') !== '' ? (string) $row->minor_description_de : (string) $row->minor_description_en,
            ],
            'major'        => [
                'code'        => (string) $row->major_id,
                'label'       => $use_german && (string) ($row->major_label_de ?? '') !== '' ? (string) $row->major_label_de : (string) $row->major_label_en,
                'description' => $use_german && (string) ($row->major_description_de ?? '') !== '' ? (string) $row->major_description_de : (string) $row->major_description_en,
            ],
        ];
    }

    private function hasTables(): bool
    {
        return DB::schema()->hasTable(OccupationSchema::TABLE_HISCO_MAJOR_GROUPS)
            && DB::schema()->hasTable(OccupationSchema::TABLE_HISCO_MINOR_GROUPS)
            && DB::schema()->hasTable(OccupationSchema::TABLE_HISCO_UNIT_GROUPS)
            && DB::schema()->hasTable(OccupationSchema::TABLE_HISCO_OCCUPATIONS);
    }

    /**
     * @param array<string,string> $files
     */
    private function catalogHash(array $files, string|null $translation_file): string
    {
        $hash_parts = array_map(static fn (string $file): string => (string) sha1_file($file), $files);
        $hash_parts[] = $translation_file !== null ? (string) sha1_file($translation_file) : 'no-hisco-hierarchy-de';

        return hash('sha1', implode('|', $hash_parts));
    }

    private function importMajorGroups(string $file): int
    {
        $count = 0;

        foreach ($this->csvRows($file) as $row) {
            $this->safeUpdateOrInsert(
                OccupationSchema::TABLE_HISCO_MAJOR_GROUPS,
                ['major_id' => (int) $row['major_id']],
                [
                    'label_en'       => $row['label'],
                    'label_de'       => null,
                    'description_en' => $row['description'],
                    'description_de' => null,
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function importMinorGroups(string $file): int
    {
        $count = 0;

        foreach ($this->csvRows($file) as $row) {
            $this->safeUpdateOrInsert(
                OccupationSchema::TABLE_HISCO_MINOR_GROUPS,
                ['minor_id' => (int) $row['minor_id']],
                [
                    'major_id'       => (int) $row['major_id'],
                    'label_en'       => $row['label'],
                    'label_de'       => null,
                    'description_en' => $row['description'],
                    'description_de' => null,
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function importUnitGroups(string $file): int
    {
        $count = 0;

        foreach ($this->csvRows($file) as $row) {
            $this->safeUpdateOrInsert(
                OccupationSchema::TABLE_HISCO_UNIT_GROUPS,
                ['unit_id' => (int) $row['unit_id']],
                [
                    'minor_id'       => (int) $row['minor_id'],
                    'label_en'       => $row['label'],
                    'label_de'       => null,
                    'description_en' => $row['description'],
                    'description_de' => null,
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function importGermanHierarchyTranslations(string $file): int
    {
        $count = 0;

        foreach ($this->csvRows($file) as $row) {
            $level = (string) ($row['level'] ?? '');
            $code = (int) ($row['code'] ?? 0);

            if ($code <= 0 && $level !== 'major') {
                continue;
            }

            $table = match ($level) {
                'major' => OccupationSchema::TABLE_HISCO_MAJOR_GROUPS,
                'minor' => OccupationSchema::TABLE_HISCO_MINOR_GROUPS,
                'unit'  => OccupationSchema::TABLE_HISCO_UNIT_GROUPS,
                default => '',
            };
            $key_column = match ($level) {
                'major' => 'major_id',
                'minor' => 'minor_id',
                'unit'  => 'unit_id',
                default => '',
            };

            if ($table === '' || $key_column === '') {
                continue;
            }

            $label_de = trim((string) ($row['label_de'] ?? ''));
            $description_de = trim((string) ($row['description_de'] ?? ''));

            DB::table($table)
                ->where($key_column, '=', $code)
                ->update([
                    'label_de'       => $label_de !== '' ? $label_de : null,
                    'description_de' => $description_de !== '' ? $description_de : null,
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);
            $count++;
        }

        return $count;
    }

    private function importOccupations(string $file): int
    {
        $count = 0;

        foreach ($this->csvRows($file) as $row) {
            $this->safeUpdateOrInsert(
                OccupationSchema::TABLE_HISCO_OCCUPATIONS,
                ['hisco_id' => (int) $row['hisco_id']],
                [
                    'unit_id'        => (int) $row['unit_id'],
                    'micro_suffix'   => (int) $row['micro_suffix'],
                    'hisco_pretty'   => $row['hisco_pretty'],
                    'label_en'       => $row['label'],
                    'description_en' => $row['description'],
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string,int|string> $attributes
     * @param array<string,int|string|null> $values
     */
    private function safeUpdateOrInsert(string $table, array $attributes, array $values): void
    {
        try {
            DB::table($table)->updateOrInsert($attributes, $values);

            return;
        } catch (QueryException $ex) {
            if ((string) $ex->getCode() !== '23000') {
                throw $ex;
            }
        }

        $query = DB::table($table);

        foreach ($attributes as $column => $value) {
            $query->where($column, '=', $value);
        }

        $query->update($values);
    }

    /**
     * @return list<array<string,string>>
     */
    private function csvRows(string $file): array
    {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return [];
        }

        $headers = [];
        $rows = [];

        while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $values = array_map(static fn (string|null $value): string => trim((string) $value), $values);

            if ($headers === []) {
                $headers = $values;
                continue;
            }

            $row = [];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = $values[$index] ?? '';
                }
            }

            $rows[] = $row;
        }

        fclose($handle);

        return array_values(array_filter($rows, static fn (array $row): bool => $row !== []));
    }

    private function normalizedCode(string $code): string
    {
        return (string) preg_replace('/[^0-9]/u', '', $code);
    }

    private function useGerman(string $language_tag): bool
    {
        return $language_tag === 'de' || str_starts_with($language_tag, 'de-');
    }

}
