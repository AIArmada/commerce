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

        commerce_schema_create_if_missing(config('feedback.database.tables.responses', 'feedback_responses'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_form_id')->index();
            $table->foreignUuid('feedback_invitation_id')->nullable()->index();

            $table->nullableUuidMorphs('subject');
            $table->nullableUuidMorphs('respondent');

            $table->string('status')->index();
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
    }
};
