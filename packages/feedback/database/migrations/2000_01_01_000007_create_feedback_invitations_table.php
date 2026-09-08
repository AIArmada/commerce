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

        commerce_schema_create_if_missing(config('feedback.database.tables.invitations', 'feedback_invitations'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_form_id')->index();
            $table->nullableUuidMorphs('recipient');

            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('token_hash')->unique();
            $table->string('status')->index();

            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('expires_at')->nullable();

            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['feedback_form_id', 'status']);
        });
    }
};
