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

        Schema::create(config('events.database.tables.venues', 'venues'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_venue_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('venue_type')->nullable()->index();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('district')->nullable()->index();
            $table->string('state')->nullable()->index();
            $table->string('postcode')->nullable();
            $table->string('country')->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_place_id')->nullable()->index();
            $table->text('google_maps_url')->nullable();
            $table->text('waze_url')->nullable();
            $table->text('map_url')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('website_url')->nullable();
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
