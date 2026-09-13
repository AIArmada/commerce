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

        Schema::create(config('events.database.tables.event_locations', 'event_locations'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->index(['event_id', 'event_occurrence_id', 'event_session_id']);
            $table->index(['event_id', 'location_role', 'sort_order']);
            $table->string('location_role')->index();
            $table->string('locationable_type')->nullable()->index();
            $table->uuid('locationable_id')->nullable()->index();
            $table->index(['locationable_type', 'locationable_id']);
            $table->uuid('venue_id')->nullable()->index();
            $table->uuid('venue_space_id')->nullable()->index();
            $table->uuid('venue_space_type_id')->nullable()->index();
            $table->string('space_name_snapshot')->nullable()->after('venue_space_type_id');
            $table->string('label')->nullable();
            $table->string('level')->nullable();
            $table->string('unit_no')->nullable();
            $table->string('visibility')->index();
            $table->string('status')->index();
            $table->integer('sort_order')->default(0)->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
