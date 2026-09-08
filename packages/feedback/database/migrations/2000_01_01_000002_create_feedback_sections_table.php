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

        commerce_schema_create_if_missing(config('feedback.database.tables.sections', 'feedback_sections'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_form_id')->index();
            $table->string('key')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('order_column')->default(0);

            $addJsonColumn($table, 'settings');
            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['feedback_form_id', 'order_column']);
        });
    }
};
