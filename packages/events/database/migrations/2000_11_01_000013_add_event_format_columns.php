<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $eventsTable = (string) config('events.database.tables.events', 'events');
        $occurrencesTable = (string) config('events.database.tables.occurrences', 'event_occurrences');

        Schema::table($eventsTable, function (Blueprint $table) use ($eventsTable): void {
            if (! Schema::hasColumn($eventsTable, 'format')) $table->string('format', 32)->default('physical')->index()->after('structure');
            if (! Schema::hasColumn($eventsTable, 'website_url')) $table->string('website_url')->nullable()->after('summary');
        });

        Schema::table($occurrencesTable, function (Blueprint $table) use ($occurrencesTable): void {
            if (! Schema::hasColumn($occurrencesTable, 'visibility')) $table->string('visibility', 32)->default('public')->index()->after('status');
            if (! Schema::hasColumn($occurrencesTable, 'format')) $table->string('format', 32)->nullable()->index()->after('visibility');
            if (! Schema::hasColumn($occurrencesTable, 'visible_at')) $table->timestampTz('visible_at')->nullable()->after('format');
            if (! Schema::hasColumn($occurrencesTable, 'postponed_at')) $table->timestampTz('postponed_at')->nullable()->after('scheduled_at');
            if (! Schema::hasColumn($occurrencesTable, 'delayed_at')) $table->timestampTz('delayed_at')->nullable()->after('postponed_at');
            if (! Schema::hasColumn($occurrencesTable, 'website_url')) $table->string('website_url')->nullable()->after('name');
            if (! Schema::hasColumn($occurrencesTable, 'livestream_url')) $table->string('livestream_url')->nullable()->after('website_url');
            if (! Schema::hasColumn($occurrencesTable, 'recording_url')) $table->string('recording_url')->nullable()->after('livestream_url');
        });
    }

    public function down(): void
    {
        $eventsTable = (string) config('events.database.tables.events', 'events');
        $occurrencesTable = (string) config('events.database.tables.occurrences', 'event_occurrences');

        Schema::table($eventsTable, fn (Blueprint $t) => $t->dropColumn(['format', 'website_url']));
        Schema::table($occurrencesTable, fn (Blueprint $t) => $t->dropColumn(['visibility', 'format', 'visible_at', 'postponed_at', 'delayed_at', 'website_url', 'livestream_url', 'recording_url']));
    }
};
