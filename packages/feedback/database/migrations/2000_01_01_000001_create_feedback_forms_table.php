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

        Schema::create(config('feedback.database.tables.forms', 'feedback_forms'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('purpose')->index();
            $table->string('status')->index();
            $table->string('visibility')->index();

            $table->nullableUuidMorphs('subject');

            $table->boolean('is_anonymous_allowed')->default(true);
            $table->boolean('is_anonymity_optional')->default(false);
            $table->boolean('is_login_required')->default(false);
            $table->boolean('is_one_response_per_respondent')->default(false);
            $table->boolean('is_edit_after_submit_allowed')->default(false);

            $table->timestampTz('opens_at')->nullable();
            $table->timestampTz('closes_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $addJsonColumn($table, 'settings');
            $addJsonColumn($table, 'metadata');

            $table->nullableUuidMorphs('created_by');

            $table->timestampsTz();

            $table->index(['status', 'visibility']);
        });
    }
};
