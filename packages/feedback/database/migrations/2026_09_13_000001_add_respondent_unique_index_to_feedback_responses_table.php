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
        $tableName = (string) config('feedback.database.table_prefix', '')
            . (string) config('feedback.database.tables.responses', 'feedback_responses');
        $columns = ['feedback_form_id', 'respondent_type', 'respondent_id'];
        $indexName = str_replace(['.', '-', ' '], '_', $tableName)
            . '_form_respondent_unique';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'enforce_respondent_uniqueness')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->boolean('enforce_respondent_uniqueness')->default(false);
            });
        }

        $indexes = Schema::getIndexes($tableName);
        $indexesToDrop = array_values(array_filter(
            $indexes,
            static fn (array $index): bool => ($index['name'] ?? null) === $indexName
                || (($index['unique'] ?? false) === true && ($index['columns'] ?? []) === $columns),
        ));

        foreach ($indexesToDrop as $index) {
            $existingIndexName = $index['name'] ?? null;

            if (! is_string($existingIndexName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($existingIndexName): void {
                $table->dropIndex($existingIndexName);
            });
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // MySQL has no portable partial-index syntax, so respondent uniqueness remains code-level there.
            return;
        }

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        $grammar = DB::connection()->getQueryGrammar();
        $wrappedColumns = implode(', ', array_map($grammar->wrap(...), $columns));

        DB::statement(sprintf(
            'create unique index %s on %s (%s) where %s = \'submitted\' and %s = 1',
            $grammar->wrap($indexName),
            $grammar->wrap($tableName),
            $wrappedColumns,
            $grammar->wrap('status'),
            $grammar->wrap('enforce_respondent_uniqueness'),
        ));
    }
};
