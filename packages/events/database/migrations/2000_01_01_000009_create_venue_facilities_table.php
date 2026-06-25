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

        Schema::create(config('events.database.tables.venue_facilities', 'venue_facilities'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('venue_id')->index();
            $table->uuid('venue_space_id')->nullable()->index();
            $table->uuid('facility_type_id')->index();
            $table->string('availability')->index();
            $table->integer('quantity')->nullable();
            $table->integer('capacity')->nullable();
            $table->boolean('is_free')->nullable();
            $table->bigInteger('fee_amount')->nullable();
            $table->string('currency')->nullable();
            $table->string('location_label')->nullable();
            $table->text('notes')->nullable();
            $table->string('visibility')->index();
            $table->timestampTz('verified_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
