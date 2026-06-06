<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $occurrencesTable = (string) config('events.database.tables.occurrences', 'commerce_event_occurrences');
        $registrationsTable = (string) config('events.database.tables.registrations', 'commerce_event_registrations');

        if (Schema::hasTable($occurrencesTable) && ! Schema::hasColumn($occurrencesTable, 'participation_mode')) {
            Schema::table($occurrencesTable, function (Blueprint $table): void {
                $table->string('participation_mode', 32)
                    ->default('registration_required')
                    ->index()
                    ->after('status');
            });
        }

        if (! Schema::hasTable($registrationsTable)) {
            return;
        }

        $hasAttendanceSource = Schema::hasColumn($registrationsTable, 'attendance_source');
        $hasAttendeeType = Schema::hasColumn($registrationsTable, 'attendee_type');
        $hasAttendeeId = Schema::hasColumn($registrationsTable, 'attendee_id');

        if (! $hasAttendanceSource || ! $hasAttendeeType || ! $hasAttendeeId) {
            Schema::table($registrationsTable, function (Blueprint $table) use ($hasAttendanceSource, $hasAttendeeType, $hasAttendeeId): void {
                if (! $hasAttendanceSource) {
                    $table->string('attendance_source', 32)
                        ->default('registration')
                        ->index()
                        ->after('participant_customer_id');
                }

                if (! $hasAttendeeType && ! $hasAttendeeId) {
                    $table->nullableUuidMorphs('attendee');

                    return;
                }

                if (! $hasAttendeeType) {
                    $table->string('attendee_type')->nullable();
                }

                if (! $hasAttendeeId) {
                    $table->uuid('attendee_id')->nullable();
                }
            });
        }

        if (Schema::hasColumn($registrationsTable, 'email')) {
            Schema::table($registrationsTable, function (Blueprint $table): void {
                $table->string('email')->nullable()->change();
            });
        }
    }
};
