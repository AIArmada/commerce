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

        Schema::create(config('events.database.tables.event_itineraries', 'event_itineraries'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->string('name');
            $table->string('itinerary_type')->index();
            $table->string('visibility')->index();
            $table->string('status')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
