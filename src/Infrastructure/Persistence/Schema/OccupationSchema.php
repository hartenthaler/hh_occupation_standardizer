<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Infrastructure\Persistence\Schema;

use Illuminate\Database\Capsule\Manager as DB;

final class OccupationSchema
{
    public const TABLE_METADATA = 'occupation_standardizer_metadata';
    public const TABLE_NORMALIZED_ENTRIES = 'occupation_standardizer_entries';

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
                $table->text('employer')->nullable();
                $table->text('type')->nullable();
                $table->text('note')->nullable();
                $table->text('source_xrefs')->nullable();
                $table->text('source_names')->nullable();
                $table->string('social_status', 255)->nullable();
                $table->string('occupation_normalized', 255)->nullable();
                $table->string('office', 255)->nullable();
                $table->string('qualification', 255)->nullable();
                $table->string('code', 64)->nullable();
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

            return;
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

        if (!DB::schema()->hasColumn(self::TABLE_NORMALIZED_ENTRIES, 'last_seen_at')) {
            DB::schema()->table(self::TABLE_NORMALIZED_ENTRIES, static function ($table): void {
                $table->timestamp('last_seen_at')->nullable();
            });
        }
    }
}
