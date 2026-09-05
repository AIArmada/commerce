<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('engagement.database.tables.engagement_counters', 'engagement_counters');

        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasIndex($table, 'engagement_counters_unique_idx')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique('engagement_counters_unique_idx');
            });
        }

        if (! Schema::hasIndex($table, 'engagement_counters_unique_idx')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique([
                    'subject_type',
                    'subject_id',
                    'counter_type',
                    'counter_key',
                    'owner_type',
                    'owner_id',
                ], 'engagement_counters_unique_idx');
            });
        }

        if (
            in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)
            && ! Schema::hasIndex($table, 'engagement_counters_global_unique_idx')
        ) {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS engagement_counters_global_unique_idx '
                . 'ON ' . $table . ' (subject_type, subject_id, counter_type, counter_key) '
                . 'WHERE owner_type IS NULL AND owner_id IS NULL',
            );
        }
    }
};
