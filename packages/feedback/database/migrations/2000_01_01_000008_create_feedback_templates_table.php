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

        commerce_schema_create_if_missing(config('feedback.database.tables.templates', 'feedback_templates'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->string('name');
            $table->string('slug')->index();
            $table->string('purpose')->index();
            $table->string('category')->nullable()->index();
            $table->string('status')->index();

            $addJsonColumn($table, 'definition');
            $addJsonColumn($table, 'settings');
            $addJsonColumn($table, 'metadata');

            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $table->timestampsTz();

            $table->index(['purpose', 'status']);
        });
    }
};
