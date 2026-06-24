<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use Hartenthaler\Webtrees\Module\OccupationStandardizer\Infrastructure\Persistence\Schema\OccupationSchema;
use Illuminate\Database\Capsule\Manager as DB;
use SimpleXMLElement;
use ZipArchive;

use function array_key_exists;
use function basename;
use function class_exists;
use function date;
use function is_file;
use function mb_strtolower;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function sha1_file;
use function str_replace;
use function strlen;
use function substr;
use function trim;

use const PATHINFO_EXTENSION;

final class OhdabSpecialDatabaseService
{
    public const SOURCE_KEY = 'ohdab_special_de';
    public const SOURCE_LANGUAGE = 'de';

    /**
     * @return array{imported:bool,row_count:int,message:string}
     */
    public function importFile(string $file, string $file_name = ''): array
    {
        $file_name = $file_name !== '' ? $file_name : basename($file);

        if (!is_file($file)) {
            return [
                'imported'  => false,
                'row_count' => 0,
                'message'   => 'The uploaded file could not be read.',
            ];
        }

        if (!class_exists(ZipArchive::class)) {
            return [
                'imported'  => false,
                'row_count' => 0,
                'message'   => 'The PHP ZIP extension is required to import XLSX files.',
            ];
        }

        if (mb_strtolower((string) pathinfo($file_name, PATHINFO_EXTENSION)) !== 'xlsx') {
            return [
                'imported'  => false,
                'row_count' => 0,
                'message'   => 'Only XLSX files can be imported.',
            ];
        }

        $file_hash = sha1_file($file);

        if ($file_hash === false) {
            return [
                'imported'  => false,
                'row_count' => 0,
                'message'   => 'The uploaded file could not be checked.',
            ];
        }

        $source = DB::table(OccupationSchema::TABLE_NORM_SOURCES)
            ->where('source_key', '=', self::SOURCE_KEY)
            ->first();

        if ($source !== null && (string) ($source->file_hash ?? '') === $file_hash) {
            return [
                'imported'  => false,
                'row_count' => (int) ($source->row_count ?? 0),
                'message'   => 'This OhdAB special database has already been imported.',
            ];
        }

        $rows = $this->xlsxRows($file);

        if ($rows === []) {
            return [
                'imported'  => false,
                'row_count' => 0,
                'message'   => 'No usable occupation rows were found in the uploaded file.',
            ];
        }

        DB::table(OccupationSchema::TABLE_NORM_SOURCES)->updateOrInsert(
            ['source_key' => self::SOURCE_KEY],
            [
                'label'      => 'OhdAB special database',
                'language'   => self::SOURCE_LANGUAGE,
                'file_name'  => basename($file_name),
                'file_hash'  => '',
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        $source_id = (int) DB::table(OccupationSchema::TABLE_NORM_SOURCES)
            ->where('source_key', '=', self::SOURCE_KEY)
            ->value('id');

        $this->deleteSourceRows($source_id);

        $concept_ids = [];
        $imported_rows = 0;

        foreach ($rows as $row) {
            $original_text = trim((string) ($row['Originalbezeichnung'] ?? ''));
            $preferred_label = trim((string) ($row['normbez'] ?? ''));
            $ohdab_full_id = trim((string) ($row['OhdAB_ges'] ?? ''));

            if ($preferred_label === '' || $ohdab_full_id === '') {
                continue;
            }

            $concept_id = $concept_ids[$ohdab_full_id] ?? $this->upsertConcept($source_id, $row);
            $concept_ids[$ohdab_full_id] = $concept_id;

            $this->upsertHierarchy($source_id, $concept_id, $row);

            if ($original_text !== '') {
                $this->upsertVariant($source_id, $concept_id, $original_text);
            }

            $imported_rows++;
        }

        DB::table(OccupationSchema::TABLE_NORM_SOURCES)
            ->where('id', '=', $source_id)
            ->update([
                'file_hash'   => $file_hash,
                'row_count'   => $imported_rows,
                'imported_at' => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

        DB::table(OccupationSchema::TABLE_METADATA)
            ->where('setting_name', 'like', 'tree_occu_%')
            ->delete();

        return [
            'imported'  => true,
            'row_count' => $imported_rows,
            'message'   => 'The OhdAB special database has been imported.',
        ];
    }

    /**
     * @return array{exists:bool,file_name:string,row_count:int,imported_at:string}
     */
    public function sourceInfo(): array
    {
        if (!DB::schema()->hasTable(OccupationSchema::TABLE_NORM_SOURCES)) {
            return [
                'exists'      => false,
                'file_name'   => '',
                'row_count'   => 0,
                'imported_at' => '',
            ];
        }

        $source = DB::table(OccupationSchema::TABLE_NORM_SOURCES)
            ->where('source_key', '=', self::SOURCE_KEY)
            ->first();

        if ($source === null) {
            return [
                'exists'      => false,
                'file_name'   => '',
                'row_count'   => 0,
                'imported_at' => '',
            ];
        }

        return [
            'exists'      => true,
            'file_name'   => (string) ($source->file_name ?? ''),
            'row_count'   => (int) ($source->row_count ?? 0),
            'imported_at' => (string) ($source->imported_at ?? ''),
        ];
    }

    /**
     * @return list<array{language:string,original_text:string,norm_concept_id:int,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,code_wikidata:string}>
     */
    public function mappings(): array
    {
        if (
            !DB::schema()->hasTable(OccupationSchema::TABLE_NORM_VARIANTS)
            || !DB::schema()->hasTable(OccupationSchema::TABLE_NORM_CONCEPTS)
        ) {
            return [];
        }

        return DB::table(OccupationSchema::TABLE_NORM_VARIANTS . ' AS variants')
            ->join(OccupationSchema::TABLE_NORM_CONCEPTS . ' AS concepts', 'concepts.id', '=', 'variants.concept_id')
            ->where('variants.language', '=', self::SOURCE_LANGUAGE)
            ->select([
                'variants.language',
                'variants.original_text',
                'concepts.id AS norm_concept_id',
                'concepts.preferred_label',
                'concepts.occupation_de_male',
                'concepts.occupation_de_female',
                'concepts.occupation_de_neutral',
                'concepts.ohdab_full_id',
                'concepts.factgrid_id',
                'concepts.wikidata_id',
            ])
            ->get()
            ->map(static fn (object $row): array => [
                'language'              => (string) $row->language,
                'original_text'         => (string) $row->original_text,
                'norm_concept_id'       => (int) $row->norm_concept_id,
                'occupation_normalized' => (string) ($row->occupation_de_male ?? $row->preferred_label),
                'occupation_de_male'    => (string) ($row->occupation_de_male ?? ''),
                'occupation_de_female'  => (string) ($row->occupation_de_female ?? ''),
                'occupation_de_neutral' => (string) ($row->occupation_de_neutral ?? $row->preferred_label),
                'occupation_en_male'    => '',
                'occupation_en_female'  => '',
                'occupation_en_neutral' => '',
                'code_hisco'            => '',
                'code_gnd'              => '',
                'code_ohdab'            => (string) $row->ohdab_full_id,
                'code_factgrid'         => (string) ($row->factgrid_id ?? ''),
                'code_wikidata'         => (string) ($row->wikidata_id ?? ''),
            ])
            ->all();
    }

    public function hierarchyPath(int $concept_id): string
    {
        if (
            $concept_id <= 0
            || !DB::schema()->hasTable(OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY)
            || !DB::schema()->hasTable(OccupationSchema::TABLE_NORM_HIERARCHY_NODES)
        ) {
            return '';
        }

        return DB::table(OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY . ' AS links')
            ->join(OccupationSchema::TABLE_NORM_HIERARCHY_NODES . ' AS nodes', 'nodes.id', '=', 'links.node_id')
            ->where('links.concept_id', '=', $concept_id)
            ->orderBy('links.position')
            ->pluck('nodes.label')
            ->filter(static fn (string $label): bool => trim($label) !== '')
            ->implode(' > ');
    }

    /**
     * @return list<array{code:string,label:string}>
     */
    public function hierarchyRows(int $concept_id): array
    {
        if (
            $concept_id <= 0
            || !DB::schema()->hasTable(OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY)
            || !DB::schema()->hasTable(OccupationSchema::TABLE_NORM_HIERARCHY_NODES)
        ) {
            return [];
        }

        return DB::table(OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY . ' AS links')
            ->join(OccupationSchema::TABLE_NORM_HIERARCHY_NODES . ' AS nodes', 'nodes.id', '=', 'links.node_id')
            ->where('links.concept_id', '=', $concept_id)
            ->orderByDesc('links.position')
            ->select(['nodes.code', 'nodes.label'])
            ->get()
            ->map(static fn (object $row): array => [
                'code'  => (string) $row->code,
                'label' => (string) $row->label,
            ])
            ->filter(static fn (array $row): bool => trim($row['code'] . $row['label']) !== '')
            ->values()
            ->all();
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
                $column = $this->columnIndex($reference);
                $values[$column] = $this->cellValue($cell, $shared_strings);
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
            $index = (int) $cell->v;

            return $shared_strings[$index] ?? '';
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

    private function deleteSourceRows(int $source_id): void
    {
        $concept_ids = DB::table(OccupationSchema::TABLE_NORM_CONCEPTS)
            ->where('source_id', '=', $source_id)
            ->pluck('id')
            ->all();

        DB::table(OccupationSchema::TABLE_NORM_VARIANTS)
            ->where('source_id', '=', $source_id)
            ->delete();

        if ($concept_ids !== []) {
            DB::table(OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY)
                ->whereIn('concept_id', $concept_ids)
                ->delete();
        }

        DB::table(OccupationSchema::TABLE_NORM_HIERARCHY_NODES)
            ->where('source_id', '=', $source_id)
            ->delete();

        DB::table(OccupationSchema::TABLE_NORM_CONCEPTS)
            ->where('source_id', '=', $source_id)
            ->delete();
    }

    /**
     * @param array<string,string> $row
     */
    private function upsertConcept(int $source_id, array $row): int
    {
        $preferred_label = trim((string) ($row['normbez'] ?? ''));
        $forms = $this->germanForms($preferred_label);

        DB::table(OccupationSchema::TABLE_NORM_CONCEPTS)->updateOrInsert(
            [
                'source_id'      => $source_id,
                'ohdab_full_id'  => trim((string) ($row['OhdAB_ges'] ?? '')),
            ],
            [
                'language'              => self::SOURCE_LANGUAGE,
                'preferred_label'       => $preferred_label,
                'occupation_de_male'    => $forms['male'],
                'occupation_de_female'  => $forms['female'],
                'occupation_de_neutral' => $forms['neutral'],
                'ohdab_class'           => trim((string) ($row['a_b'] ?? '')),
                'ohdab_group'           => trim((string) ($row['ohdab'] ?? '')),
                'ohdab_individual'      => trim((string) ($row['ind'] ?? '')),
                'factgrid_id'           => $this->factgridId(trim((string) ($row['QFact'] ?? ''))),
                'wikidata_id'           => $this->wikidataId($row),
                'requirement_level'     => trim((string) ($row['anford'] ?? '')),
                'requirement_label'     => trim((string) ($row['anford_txt'] ?? '')),
                'updated_at'            => date('Y-m-d H:i:s'),
            ]
        );

        return (int) DB::table(OccupationSchema::TABLE_NORM_CONCEPTS)
            ->where('source_id', '=', $source_id)
            ->where('ohdab_full_id', '=', trim((string) ($row['OhdAB_ges'] ?? '')))
            ->value('id');
    }

    /**
     * @param array<string,string> $row
     */
    private function upsertHierarchy(int $source_id, int $concept_id, array $row): void
    {
        $class = trim((string) ($row['a_b'] ?? ''));
        $group = trim((string) ($row['ohdab'] ?? ''));
        $parent_id = null;

        for ($level = 1; $level <= 5; $level++) {
            $label = trim((string) ($row['OhdAB_0' . $level] ?? ''));

            if ($class === '' || $group === '' || $label === '') {
                continue;
            }

            $code = $class . ' ' . substr($group, 0, $level);
            $node_id = $this->upsertHierarchyNode($source_id, $level, $code, $label, $parent_id);

            DB::table(OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY)->updateOrInsert(
                [
                    'concept_id' => $concept_id,
                    'node_id'    => $node_id,
                ],
                [
                    'position' => $level,
                ]
            );

            $parent_id = $node_id;
        }
    }

    private function upsertHierarchyNode(int $source_id, int $level, string $code, string $label, int|null $parent_id): int
    {
        DB::table(OccupationSchema::TABLE_NORM_HIERARCHY_NODES)->updateOrInsert(
            [
                'source_id' => $source_id,
                'language'  => self::SOURCE_LANGUAGE,
                'code'      => $code,
            ],
            [
                'level'      => $level,
                'label'      => $label,
                'parent_id'  => $parent_id,
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        return (int) DB::table(OccupationSchema::TABLE_NORM_HIERARCHY_NODES)
            ->where('source_id', '=', $source_id)
            ->where('language', '=', self::SOURCE_LANGUAGE)
            ->where('code', '=', $code)
            ->value('id');
    }

    private function upsertVariant(int $source_id, int $concept_id, string $original_text): void
    {
        DB::table(OccupationSchema::TABLE_NORM_VARIANTS)->updateOrInsert(
            [
                'source_id'    => $source_id,
                'language'     => self::SOURCE_LANGUAGE,
                'original_key' => $this->matchKey($original_text),
            ],
            [
                'concept_id'    => $concept_id,
                'original_text' => $original_text,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @return array{male:string,female:string,neutral:string}
     */
    private function germanForms(string $label): array
    {
        $neutral = trim($label);
        $male = $neutral;
        $female = '';

        if (preg_match('/^(.+?)\/(.+)$/u', $neutral, $match) === 1) {
            $male = trim($match[1]);
            $female = trim($match[2]);

            if (substr($female, 0, 1) === '-') {
                $female = '';
            }

            if (preg_match('/^(.+)\/in(.*)$/u', $neutral, $in_match) === 1) {
                $male = trim($in_match[1] . $in_match[2]);
                $female = trim($in_match[1] . 'in' . $in_match[2]);
            }
        }

        return [
            'male'    => $male,
            'female'  => $female,
            'neutral' => $neutral,
        ];
    }

    private function factgridId(string $value): string
    {
        if (preg_match('/Item:(Q[0-9]+)/u', $value, $match) === 1) {
            return $match[1];
        }

        return $value;
    }

    /**
     * @param array<string,string> $row
     */
    private function wikidataId(array $row): string
    {
        foreach (['Wikidata', 'wikidata', 'QWikidata', 'QWiki'] as $column) {
            $value = trim((string) ($row[$column] ?? ''));

            if ($value === '') {
                continue;
            }

            if (preg_match('/(Q[0-9]+)/u', $value, $match) === 1) {
                return $match[1];
            }

            return $value;
        }

        return '';
    }

    private function matchKey(string $value): string
    {
        $key = mb_strtolower(trim($value));
        $key = preg_replace('/\s+/u', ' ', $key) ?? $key;

        return str_replace('–', '-', $key);
    }
}
