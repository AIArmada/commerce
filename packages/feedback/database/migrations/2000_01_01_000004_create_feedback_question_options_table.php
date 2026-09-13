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

        Schema::create(config('feedback.database.tables.question_options', 'feedback_question_options'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_question_id')->index();

            $table->string('label');
            $table->string('value')->index();
            $table->decimal('score', 10, 2)->nullable();
            $table->unsignedInteger('order_column')->default(0);

            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['feedback_question_id', 'order_column']);
        });
    }
};
