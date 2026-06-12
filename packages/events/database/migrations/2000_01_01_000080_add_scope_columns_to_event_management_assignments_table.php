<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('events.database.tables.event_management_assignments', 'event_management_assignments'), function (Blueprint $table): void {
            $table->uuid('event_id')->nullable()->after('id')->index();
            $table->uuid('event_occurrence_id')->nullable()->after('event_id')->index();
            $table->uuid('event_session_id')->nullable()->after('event_occurrence_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table(config('events.database.tables.event_management_assignments', 'event_management_assignments'), function (Blueprint $table): void {
            $table->dropColumn(['event_id', 'event_occurrence_id', 'event_session_id']);
        });
    }
};
