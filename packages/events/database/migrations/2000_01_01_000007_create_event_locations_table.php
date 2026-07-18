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
            $table->string('label')->nullable();
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('state')->nullable()->index();
            $table->string('postcode', 20)->nullable();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('country')->nullable();
            $table->string('level')->nullable();
            $table->string('unit_no')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_place_id')->nullable()->index();
            $table->text('google_maps_url')->nullable();
            $table->text('waze_url')->nullable();
            $table->text('map_url')->nullable();
            $table->text('directions')->nullable();
            $table->{$jsonType}('address_snapshot')->nullable();
            $table->timestampTz('geocoded_at')->nullable();
            $table->string('geocoding_source')->nullable();
            $table->string('visibility')->index();
            $table->string('status')->index();
            $table->integer('sort_order')->default(0)->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
