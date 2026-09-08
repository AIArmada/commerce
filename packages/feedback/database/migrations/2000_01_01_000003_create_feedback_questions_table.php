<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $jsonColumnType = commerce_json_column_type('feedback', 'jsonb');

        $addJsonColumn = function (Blueprint $table, string $column) use ($jsonColumnType): void {
            $table->{$jsonColumnType}($column)->nullable();
        };

        commerce_schema_create_if_missing(config('feedback.database.tables.questions', 'feedback_questions'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_form_id')->index();
            $table->foreignUuid('feedback_section_id')->nullable()->index();

            $table->string('key')->index();
            $table->string('type')->index();
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('help_text')->nullable();
            $table->string('placeholder')->nullable();

            $table->boolean('is_required')->default(false);
            $table->boolean('is_scored')->default(false);
            $table->unsignedInteger('order_column')->default(0);

            $addJsonColumn($table, 'validation_rules');
            $addJsonColumn($table, 'visibility_rules');
            $addJsonColumn($table, 'scoring_rules');
            $addJsonColumn($table, 'settings');
            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['feedback_form_id', 'key']);
            $table->index(['feedback_form_id', 'order_column']);
        });
    }
};
