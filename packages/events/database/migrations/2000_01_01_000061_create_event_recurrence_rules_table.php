<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('events', 'jsonb');

        Schema::create(config('events.database.tables.event_recurrence_rules', 'event_recurrence_rules'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('recurrence_target_type')->nullable()->index();
            $table->string('recurrence_target_id')->nullable()->index();
            $table->string('code')->nullable()->index();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('recurrence_type')->index();
            $table->string('frequency')->index();
            $table->integer('interval')->default(1);
            $table->{$jsonType}('days_of_week')->nullable();
            $table->{$jsonType}('days_of_month')->nullable();
            $table->{$jsonType}('months_of_year')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->integer('max_occurrences')->nullable();
            $table->string('timezone');
            $table->string('time_mode')->nullable();
            $table->time('starts_at_time')->nullable();
            $table->time('ends_at_time')->nullable();
            $table->string('anchor_type')->nullable();
            $table->string('anchor_code')->nullable();
            $table->string('relation')->nullable();
            $table->integer('offset_minutes')->nullable();
            $table->text('rrule_text')->nullable();
            $table->string('human_readable_rule')->nullable();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->timestampTz('generated_until')->nullable();
            $table->timestampTz('last_generated_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
