<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('events', 'jsonb');

        Schema::create(config('events.database.tables.venue_spaces', 'venue_spaces'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('venue_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->after('name');
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

        $grammar = DB::connection()->getQueryGrammar();
        $tableName = (string) config('events.database.tables.venue_spaces', 'venue_spaces');
        $wrappedTable = $grammar->wrapTable($tableName);
        $catalogIndex = $grammar->wrap($tableName . '_catalog_slug_unique');
        $venueIndex = $grammar->wrap($tableName . '_venue_slug_unique');

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s IS NULL',
            $catalogIndex,
            $wrappedTable,
            $grammar->wrap('slug'),
            $grammar->wrap('venue_id'),
        ));

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s, %s) WHERE %s IS NOT NULL',
            $venueIndex,
            $wrappedTable,
            $grammar->wrap('venue_id'),
            $grammar->wrap('slug'),
            $grammar->wrap('venue_id'),
        ));
    }
};
