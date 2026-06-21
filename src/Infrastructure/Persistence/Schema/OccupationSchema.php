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
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
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
            'occupation_de_male'   => 255,
            'occupation_de_female' => 255,
            'occupation_de_neutral' => 255,
            'occupation_en_male'   => 255,
            'occupation_en_female' => 255,
            'occupation_en_neutral' => 255,
        ] as $column => $length) {
            if (!DB::schema()->hasColumn(self::TABLE_NORMALIZED_ENTRIES, $column)) {
                DB::schema()->table(self::TABLE_NORMALIZED_ENTRIES, static function ($table) use ($column, $length): void {
                    $table->string($column, $length)->nullable();
                });
            }
        }

        foreach ([
            'occupation_de_neutral' => 255,
            'occupation_en_neutral' => 255,
        ] as $column => $length) {
            if (!DB::schema()->hasColumn(self::TABLE_NORMALIZATION_TERMS, $column)) {
                DB::schema()->table(self::TABLE_NORMALIZATION_TERMS, static function ($table) use ($column, $length): void {
                    $table->string($column, $length)->nullable();
                });
            }
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
            DB::table(self::TABLE_NORMALIZATION_TERMS)->updateOrInsert(
                ['normalized_key' => $rule['occupation_normalized']],
                [
                    'occupation_de_male'    => $rule['occupation_normalized'],
                    'occupation_de_female'  => $rule['occupation_de_female'],
                    'occupation_de_neutral' => $rule['occupation_de_neutral'],
                    'updated_at'            => date('Y-m-d H:i:s'),
                ]
            );

            $term = DB::table(self::TABLE_NORMALIZATION_TERMS)
                ->where('normalized_key', '=', $rule['occupation_normalized'])
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
}
