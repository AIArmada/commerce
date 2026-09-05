<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('events', 'jsonb');

        commerce_schema_create_if_missing(config('events.database.tables.event_registration_questions', 'event_registration_questions'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('field_key', 80);
            $table->string('question', 500);
            $table->text('description')->nullable();
            $table->string('type', 32);
            $table->{$jsonType}('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('status', 32)->default('active')->index();
            $table->timestampTz('archived_at')->nullable();
            $table->unsignedInteger('order_column')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(
                ['event_id', 'event_occurrence_id', 'event_session_id', 'field_key'],
                'event_registration_questions_scope_field_key_index',
            );
        });
    }
};
