<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('events.database.tables.event_headcount_logs', 'event_headcount_logs'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->nullableUuidMorphs('recorded_by');
            $table->unsignedInteger('count');
            $table->timestampTz('recorded_at');
            $table->string('interval_label')->nullable();
            $table->text('notes')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestampsTz();
        });
    }
};
