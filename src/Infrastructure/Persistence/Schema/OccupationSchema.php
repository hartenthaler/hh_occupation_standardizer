<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Infrastructure\Persistence\Schema;

use Illuminate\Database\Capsule\Manager as DB;

use function date;

final class OccupationSchema
{
    public const TABLE_METADATA = 'occupation_standardizer_metadata';
    public const TABLE_NORMALIZED_ENTRIES = 'occupation_standardizer_entries';
    public const TABLE_NORMALIZATION_RULES = 'occupation_standardizer_rules';
    public const TABLE_NORMALIZATION_TERMS = 'occupation_standardizer_terms';
    public const TABLE_NORM_SOURCES = 'occupation_standardizer_norm_sources';
    public const TABLE_NORM_CONCEPTS = 'occupation_standardizer_norm_concepts';
    public const TABLE_NORM_HIERARCHY_NODES = 'occupation_standardizer_norm_hierarchy_nodes';
    public const TABLE_NORM_CONCEPT_HIERARCHY = 'occupation_standardizer_norm_concept_hierarchy';
    public const TABLE_NORM_VARIANTS = 'occupation_standardizer_norm_variants';
    public const TABLE_HISCO_MAJOR_GROUPS = 'occupation_standardizer_hisco_major_groups';
    public const TABLE_HISCO_MINOR_GROUPS = 'occupation_standardizer_hisco_minor_groups';
    public const TABLE_HISCO_UNIT_GROUPS = 'occupation_standardizer_hisco_unit_groups';
    public const TABLE_HISCO_OCCUPATIONS = 'occupation_standardizer_hisco_occupations';

    public function ensureSchema(): void
    {
        if (!DB::schema()->hasTable(self::TABLE_METADATA)) {
            DB::schema()->create(self::TABLE_METADATA, static function ($table): void {
                $table->string('setting_name', 64)->primary();
                $table->text('setting_value');
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_NORMALIZED_ENTRIES)) {
            DB::schema()->create(self::TABLE_NORMALIZED_ENTRIES, static function ($table): void {
                $table->increments('id');
                $table->char('entry_key', 40)->unique();
                $table->integer('tree_id');
                $table->string('individual_xref', 32);
                $table->string('fact_id', 128);
                $table->integer('part_index');
                $table->text('original_fact_text');
                $table->text('original_part_text');
                $table->string('date', 255)->nullable();
                $table->text('place')->nullable();
                $table->string('location_xref', 32)->nullable();
                $table->text('location_hierarchy')->nullable();
                $table->text('employer')->nullable();
                $table->text('type')->nullable();
                $table->text('note')->nullable();
                $table->text('source_xrefs')->nullable();
                $table->text('source_names')->nullable();
                $table->string('language', 35)->nullable();
                $table->string('social_status', 255)->nullable();
                $table->string('occupation_normalized', 255)->nullable();
                $table->string('occupation_de_male', 255)->nullable();
                $table->string('occupation_de_female', 255)->nullable();
                $table->string('occupation_de_neutral', 255)->nullable();
                $table->string('occupation_en_male', 255)->nullable();
                $table->string('occupation_en_female', 255)->nullable();
                $table->string('occupation_en_neutral', 255)->nullable();
                $table->string('office', 255)->nullable();
                $table->string('qualification', 255)->nullable();
                $table->string('code_hisco', 64)->nullable();
                $table->string('code_gnd', 64)->nullable();
                $table->string('code_ohdab', 64)->nullable();
                $table->string('code_factgrid', 64)->nullable();
                $table->string('code_wikidata', 64)->nullable();
                $table->integer('norm_concept_id')->nullable();
                $table->string('status', 32);
                $table->boolean('reviewed')->default(false);
                $table->boolean('manually_changed')->default(false);
                $table->boolean('is_active')->default(true);
                $table->string('rule_numbers', 255);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();

                $table->index(['tree_id', 'individual_xref'], 'idx_occ_std_indi');
                $table->index(['tree_id', 'fact_id'], 'idx_occ_std_fact');
                $table->index(['tree_id', 'status'], 'idx_occ_std_status');
                $table->index(['tree_id', 'is_active'], 'idx_occ_std_active');
            });

        }

        if (!DB::schema()->hasTable(self::TABLE_NORMALIZATION_RULES)) {
            DB::schema()->create(self::TABLE_NORMALIZATION_RULES, static function ($table): void {
                $table->increments('id');
                $table->string('language', 35);
                $table->string('original_text', 255);
                $table->integer('normalized_term_id')->nullable();
                $table->string('social_status', 255)->nullable();
                $table->string('qualification', 255)->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->unique(['language', 'original_text'], 'idx_occ_std_rule_text');
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_NORMALIZATION_TERMS)) {
            DB::schema()->create(self::TABLE_NORMALIZATION_TERMS, static function ($table): void {
                $table->increments('id');
                $table->string('language', 35)->nullable();
                $table->string('normalized_key', 255)->unique();
                $table->string('occupation_de_male', 255)->nullable();
                $table->string('occupation_de_female', 255)->nullable();
                $table->string('occupation_de_neutral', 255)->nullable();
                $table->string('occupation_en_male', 255)->nullable();
                $table->string('occupation_en_female', 255)->nullable();
                $table->string('occupation_en_neutral', 255)->nullable();
                $table->string('code_hisco', 64)->nullable();
                $table->string('code_gnd', 64)->nullable();
                $table->string('code_ohdab', 64)->nullable();
                $table->string('code_factgrid', 64)->nullable();
                $table->string('code_wikidata', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_NORM_SOURCES)) {
            DB::schema()->create(self::TABLE_NORM_SOURCES, static function ($table): void {
                $table->increments('id');
                $table->string('source_key', 64)->unique();
                $table->string('label', 255);
                $table->string('language', 35);
                $table->string('file_name', 255)->nullable();
                $table->char('file_hash', 40)->nullable();
                $table->integer('row_count')->default(0);
                $table->timestamp('imported_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_NORM_CONCEPTS)) {
            DB::schema()->create(self::TABLE_NORM_CONCEPTS, static function ($table): void {
                $table->increments('id');
                $table->integer('source_id');
                $table->string('language', 35);
                $table->string('preferred_label', 255);
                $table->string('occupation_de_male', 255)->nullable();
                $table->string('occupation_de_female', 255)->nullable();
                $table->string('occupation_de_neutral', 255)->nullable();
                $table->string('ohdab_full_id', 64);
                $table->string('ohdab_class', 8)->nullable();
                $table->string('ohdab_group', 32)->nullable();
                $table->string('ohdab_individual', 32)->nullable();
                $table->string('factgrid_id', 64)->nullable();
                $table->string('wikidata_id', 64)->nullable();
                $table->string('requirement_level', 32)->nullable();
                $table->string('requirement_label', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->unique(['source_id', 'ohdab_full_id'], 'idx_occ_std_norm_concept');
                $table->index(['source_id', 'language'], 'idx_occ_std_norm_concept_lang');
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_NORM_HIERARCHY_NODES)) {
            DB::schema()->create(self::TABLE_NORM_HIERARCHY_NODES, static function ($table): void {
                $table->increments('id');
                $table->integer('source_id');
                $table->string('language', 35);
                $table->integer('level');
                $table->string('code', 64);
                $table->text('label');
                $table->integer('parent_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->unique(['source_id', 'language', 'code'], 'idx_occ_std_norm_node');
                $table->index(['source_id', 'parent_id'], 'idx_occ_std_norm_node_parent');
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_NORM_CONCEPT_HIERARCHY)) {
            DB::schema()->create(self::TABLE_NORM_CONCEPT_HIERARCHY, static function ($table): void {
                $table->increments('id');
                $table->integer('concept_id');
                $table->integer('node_id');
                $table->integer('position');

                $table->unique(['concept_id', 'node_id'], 'idx_occ_std_norm_concept_node');
                $table->index(['concept_id', 'position'], 'idx_occ_std_norm_concept_pos');
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_NORM_VARIANTS)) {
            DB::schema()->create(self::TABLE_NORM_VARIANTS, static function ($table): void {
                $table->increments('id');
                $table->integer('source_id');
                $table->integer('concept_id');
                $table->string('language', 35);
                $table->string('original_text', 255);
                $table->string('original_key', 255);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->unique(['source_id', 'language', 'original_key'], 'idx_occ_std_norm_variant');
                $table->index(['concept_id'], 'idx_occ_std_norm_variant_concept');
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_HISCO_MAJOR_GROUPS)) {
            DB::schema()->create(self::TABLE_HISCO_MAJOR_GROUPS, static function ($table): void {
                $table->unsignedTinyInteger('major_id')->primary();
                $table->string('label_en', 255);
                $table->string('label_de', 255)->nullable();
                $table->text('description_en');
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_HISCO_MINOR_GROUPS)) {
            DB::schema()->create(self::TABLE_HISCO_MINOR_GROUPS, static function ($table): void {
                $table->unsignedTinyInteger('minor_id')->primary();
                $table->unsignedTinyInteger('major_id');
                $table->string('label_en', 255);
                $table->string('label_de', 255)->nullable();
                $table->text('description_en');
                $table->timestamp('updated_at')->nullable();

                $table->index('major_id', 'idx_occ_std_hisco_minor_major');
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_HISCO_UNIT_GROUPS)) {
            DB::schema()->create(self::TABLE_HISCO_UNIT_GROUPS, static function ($table): void {
                $table->unsignedSmallInteger('unit_id')->primary();
                $table->unsignedTinyInteger('minor_id');
                $table->string('label_en', 255);
                $table->text('description_en');
                $table->timestamp('updated_at')->nullable();

                $table->index('minor_id', 'idx_occ_std_hisco_unit_minor');
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_HISCO_OCCUPATIONS)) {
            DB::schema()->create(self::TABLE_HISCO_OCCUPATIONS, static function ($table): void {
                $table->unsignedMediumInteger('hisco_id')->primary();
                $table->unsignedSmallInteger('unit_id');
                $table->unsignedTinyInteger('micro_suffix');
                $table->string('hisco_pretty', 10);
                $table->string('label_en', 255);
                $table->text('description_en');
                $table->timestamp('updated_at')->nullable();

                $table->index('hisco_pretty', 'idx_occ_std_hisco_pretty');
                $table->index('unit_id', 'idx_occ_std_hisco_occupation_unit');
            });
        }

        if (!DB::schema()->hasColumn(self::TABLE_NORMALIZATION_RULES, 'normalized_term_id')) {
            DB::schema()->table(self::TABLE_NORMALIZATION_RULES, static function ($table): void {
                $table->integer('normalized_term_id')->nullable();
            });
        }

        if (!DB::schema()->hasColumn(self::TABLE_NORMALIZED_ENTRIES, 'is_active')) {
            DB::schema()->table(self::TABLE_NORMALIZED_ENTRIES, static function ($table): void {
                $table->boolean('is_active')->default(true);
                $table->index(['tree_id', 'is_active'], 'idx_occ_std_active');
            });
        }

        if (!DB::schema()->hasColumn(self::TABLE_NORMALIZED_ENTRIES, 'manually_changed')) {
            DB::schema()->table(self::TABLE_NORMALIZED_ENTRIES, static function ($table): void {
                $table->boolean('manually_changed')->default(false);
            });
        }

        foreach ([
            'language'      => 35,
            'location_xref' => 32,
            'code_hisco'    => 64,
            'code_gnd'      => 64,
            'code_ohdab'    => 64,
            'code_factgrid' => 64,
            'code_wikidata' => 64,
            'norm_concept_id' => 11,
            'occupation_de_male'   => 255,
            'occupation_de_female' => 255,
            'occupation_de_neutral' => 255,
            'occupation_en_male'   => 255,
            'occupation_en_female' => 255,
            'occupation_en_neutral' => 255,
        ] as $column => $length) {
            if (!DB::schema()->hasColumn(self::TABLE_NORMALIZED_ENTRIES, $column)) {
                DB::schema()->table(self::TABLE_NORMALIZED_ENTRIES, static function ($table) use ($column, $length): void {
                    if ($column === 'norm_concept_id') {
                        $table->integer($column)->nullable();
                    } else {
                        $table->string($column, $length)->nullable();
                    }
                });
            }
        }

        foreach ([
            'language'              => 35,
            'occupation_de_neutral' => 255,
            'occupation_en_neutral' => 255,
            'code_factgrid'         => 64,
            'code_wikidata'         => 64,
        ] as $column => $length) {
            if (!DB::schema()->hasColumn(self::TABLE_NORMALIZATION_TERMS, $column)) {
                DB::schema()->table(self::TABLE_NORMALIZATION_TERMS, static function ($table) use ($column, $length): void {
                    $table->string($column, $length)->nullable();
                });
            }
        }

        $this->deriveNormalizedTermKeys();

        if (!DB::schema()->hasColumn(self::TABLE_NORM_CONCEPTS, 'wikidata_id')) {
            DB::schema()->table(self::TABLE_NORM_CONCEPTS, static function ($table): void {
                $table->string('wikidata_id', 64)->nullable();
            });
        }

        if (!DB::schema()->hasColumn(self::TABLE_NORMALIZED_ENTRIES, 'location_hierarchy')) {
            DB::schema()->table(self::TABLE_NORMALIZED_ENTRIES, static function ($table): void {
                $table->text('location_hierarchy')->nullable();
            });
        }

        if (!DB::schema()->hasColumn(self::TABLE_NORMALIZED_ENTRIES, 'last_seen_at')) {
            DB::schema()->table(self::TABLE_NORMALIZED_ENTRIES, static function ($table): void {
                $table->timestamp('last_seen_at')->nullable();
            });
        }

        $this->seedDefaultNormalizationRules();
    }

    private function seedDefaultNormalizationRules(): void
    {
        foreach ([
            ['language' => 'de', 'original_text' => 'Ärztin', 'occupation_normalized' => 'Arzt', 'occupation_de_female' => 'Ärztin', 'occupation_de_neutral' => 'Arzt/Ärztin'],
            ['language' => 'de', 'original_text' => 'Beck', 'occupation_normalized' => 'Bäcker', 'occupation_de_female' => 'Bäckerin', 'occupation_de_neutral' => 'Bäcker/in'],
            ['language' => 'de', 'original_text' => 'Kieffer', 'occupation_normalized' => 'Küfer', 'occupation_de_female' => 'Küferin', 'occupation_de_neutral' => 'Küfer/in'],
            ['language' => 'de', 'original_text' => 'Orgelbauerin', 'occupation_normalized' => 'Orgelbauer', 'occupation_de_female' => 'Orgelbauerin', 'occupation_de_neutral' => 'Orgelbauer/in'],
            ['language' => 'de', 'original_text' => 'Schuster', 'occupation_normalized' => 'Schuhmacher', 'occupation_de_female' => 'Schuhmacherin', 'occupation_de_neutral' => 'Schuhmacher/in'],
        ] as $rule) {
            $normalized_key = $this->normalizedTermKey($rule['language'], $rule['occupation_normalized']);

            DB::table(self::TABLE_NORMALIZATION_TERMS)->updateOrInsert(
                ['normalized_key' => $normalized_key],
                [
                    'language'              => $rule['language'],
                    'occupation_de_male'    => $rule['occupation_normalized'],
                    'occupation_de_female'  => $rule['occupation_de_female'],
                    'occupation_de_neutral' => $rule['occupation_de_neutral'],
                    'updated_at'            => date('Y-m-d H:i:s'),
                ]
            );

            $term = DB::table(self::TABLE_NORMALIZATION_TERMS)
                ->where('normalized_key', '=', $normalized_key)
                ->first();

            $exists = DB::table(self::TABLE_NORMALIZATION_RULES)
                ->where('language', '=', $rule['language'])
                ->where('original_text', '=', $rule['original_text'])
                ->exists();

            if (!$exists) {
                DB::table(self::TABLE_NORMALIZATION_RULES)->insert([
                    'language'           => $rule['language'],
                    'original_text'      => $rule['original_text'],
                    'normalized_term_id' => $term !== null ? (int) $term->id : null,
                    'enabled'            => true,
                ]);
            } elseif ($term !== null) {
                DB::table(self::TABLE_NORMALIZATION_RULES)
                    ->where('language', '=', $rule['language'])
                    ->where('original_text', '=', $rule['original_text'])
                    ->whereNull('normalized_term_id')
                    ->update(['normalized_term_id' => (int) $term->id]);
            }
        }
    }

    private function deriveNormalizedTermKeys(): void
    {
        foreach (DB::table(self::TABLE_NORMALIZATION_TERMS)->get() as $term) {
            $language = trim((string) ($term->language ?? '')) !== '' ? trim((string) $term->language) : 'de';
            $occupation = $this->keyMasculineForm(
                $language,
                (string) ($term->occupation_de_male ?? ''),
                (string) ($term->occupation_en_male ?? '')
            );

            if ($occupation === '') {
                $occupation = trim((string) $term->normalized_key);
            }

            if ($occupation === '') {
                continue;
            }

            $normalized_key = $this->normalizedTermKey($language, $occupation);

            if ((string) ($term->language ?? '') === $language && (string) $term->normalized_key === $normalized_key) {
                continue;
            }

            DB::table(self::TABLE_NORMALIZATION_TERMS)
                ->where('id', '=', (int) $term->id)
                ->update([
                    'language'        => $language,
                    'normalized_key'  => $normalized_key,
                    'updated_at'      => date('Y-m-d H:i:s'),
                ]);
        }
    }

    private function normalizedTermKey(string $language, string $occupation): string
    {
        return trim($language) . ':' . trim($occupation);
    }

    private function keyMasculineForm(string $language, string $occupation_de_male, string $occupation_en_male): string
    {
        $primary_language = explode('-', trim($language))[0] ?? '';

        if ($primary_language === 'en') {
            return trim($occupation_en_male) !== '' ? trim($occupation_en_male) : trim($occupation_de_male);
        }

        return trim($occupation_de_male) !== '' ? trim($occupation_de_male) : trim($occupation_en_male);
    }
}
