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

        Schema::create(config('events.database.tables.venues', 'venues'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_venue_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('venue_type')->nullable()->index();
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('state')->nullable()->index();
            $table->string('postcode', 20)->nullable();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_place_id')->nullable()->index();
            $table->text('google_maps_url')->nullable();
            $table->text('waze_url')->nullable();
            $table->text('map_url')->nullable();
            $table->text('directions')->nullable();
            $table->timestampTz('geocoded_at')->nullable();
            $table->string('geocoding_source')->nullable();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
