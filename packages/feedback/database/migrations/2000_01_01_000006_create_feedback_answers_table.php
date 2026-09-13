<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonColumnType = commerce_json_column_type('feedback', 'jsonb');

        $addJsonColumn = function (Blueprint $table, string $column) use ($jsonColumnType): void {
            $table->{$jsonColumnType}($column)->nullable();
        };

        Schema::create(config('feedback.database.tables.answers', 'feedback_answers'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_response_id')->index();
            $table->foreignUuid('feedback_question_id')->index();

            $addJsonColumn($table, 'value');

            $table->text('text_value')->nullable();
            $table->decimal('number_value', 12, 4)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->date('date_value')->nullable();
            $table->timestampTz('datetime_value')->nullable();

            $table->decimal('score', 10, 2)->nullable();

            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['feedback_response_id', 'feedback_question_id']);
            $table->index(['feedback_question_id', 'number_value']);
            $table->index(['feedback_question_id', 'score']);
        });
    }
};
