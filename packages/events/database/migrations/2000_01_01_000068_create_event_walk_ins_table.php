<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        commerce_schema_create_if_missing(config('events.database.tables.event_walk_ins', 'event_walk_ins'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->nullableUuidMorphs('recorded_by');
            $table->unsignedInteger('count')->default(1);
            $table->timestampTz('recorded_at');
            $table->text('notes')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestampsTz();
        });
    }
};
