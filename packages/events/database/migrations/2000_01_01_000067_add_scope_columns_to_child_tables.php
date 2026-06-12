<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add event_id + event_session_id to event_registration_participants
        Schema::table(config('events.database.tables.event_registration_participants', 'event_registration_participants'), function (Blueprint $table): void {
            if (! Schema::hasColumn(config('events.database.tables.event_registration_participants', 'event_registration_participants'), 'event_id')) {
                $table->uuid('event_id')->nullable()->after('id')->index();
            }
        });

        // Add scope columns to event_registration_items
        Schema::table(config('events.database.tables.event_registration_items', 'event_registration_items'), function (Blueprint $table): void {
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
        });

        // Add scope columns to event_registration_answers
        Schema::table(config('events.database.tables.event_registration_answers', 'event_registration_answers'), function (Blueprint $table): void {
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
        });

        // Add event_id + event_occurrence_id to event_itinerary_items
        Schema::table(config('events.database.tables.event_itinerary_items', 'event_itinerary_items'), function (Blueprint $table): void {
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
        });
    }
};
