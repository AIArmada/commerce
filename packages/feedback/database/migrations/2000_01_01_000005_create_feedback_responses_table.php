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
        $jsonColumnType = commerce_json_column_type('feedback', 'jsonb');
        $tableName = config('feedback.database.tables.responses', 'feedback_responses');
        $indexName = str_replace(['.', '-', ' '], '_', (string) $tableName)
            . '_form_respondent_unique';

        $addJsonColumn = function (Blueprint $table, string $column) use ($jsonColumnType): void {
            $table->{$jsonColumnType}($column)->nullable();
        };

        Schema::create($tableName, function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_form_id')->index();
            $table->foreignUuid('feedback_invitation_id')->nullable()->index();

            $table->nullableUuidMorphs('subject');
            $table->nullableUuidMorphs('respondent');

            $table->string('status')->index();
            $table->boolean('enforce_respondent_uniqueness')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_editable')->default(false);

            $table->decimal('score', 10, 2)->nullable();
            $table->decimal('max_score', 10, 2)->nullable();

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('marked_spam_at')->nullable();

            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['feedback_form_id', 'status']);
            $table->index(['submitted_at']);
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // MySQL has no portable partial-index syntax, so respondent uniqueness remains code-level there.
            return;
        }

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        $columns = ['feedback_form_id', 'respondent_type', 'respondent_id'];
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
