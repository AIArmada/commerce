<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('events.database.json_column_type', 'jsonb');

        Schema::create(config('events.database.tables.event_attendances', 'event_attendances'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->uuid('event_registration_id')->nullable()->index();
            $table->uuid('event_registration_participant_id')->nullable()->index();
            $table->uuid('event_pass_id')->nullable()->index();
            $table->string('attendee_type')->nullable()->index();
            $table->uuid('attendee_id')->nullable()->index();
            $table->string('attendance_type')->index();
            $table->timestampTz('checked_in_at')->nullable();
            $table->timestampTz('checked_out_at')->nullable();
            $table->string('check_in_source')->nullable()->index();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('corrected_at')->nullable();
            $table->text('notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
