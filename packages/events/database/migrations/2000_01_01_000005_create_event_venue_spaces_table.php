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

        Schema::create(config('events.database.tables.venue_spaces', 'venue_spaces'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('venue_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique()->after('name');
            $table->string('code')->nullable()->index();
            $table->string('space_type')->nullable()->index();
            $table->string('level')->nullable();
            $table->string('unit_no')->nullable();
            $table->string('block')->nullable();
            $table->string('wing')->nullable();
            $table->integer('capacity')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('google_maps_url')->nullable();
            $table->text('waze_url')->nullable();
            $table->text('map_url')->nullable();
            $table->text('directions')->nullable();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
