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
        $venueSpacesTable = (string) config('events.database.tables.venue_spaces', 'venue_spaces');
        $eventLocationsTable = (string) config('events.database.tables.event_locations', 'event_locations');

        if (! Schema::hasColumn($eventLocationsTable, 'space_name_snapshot')) {
            Schema::table($eventLocationsTable, function (Blueprint $table): void {
                $table->string('space_name_snapshot')->nullable()->after('venue_space_type_id');
            });
        }

        $grammar = DB::connection()->getQueryGrammar();
        $wrappedTable = $grammar->wrapTable($venueSpacesTable);
        $wrappedLocationsTable = $grammar->wrapTable($eventLocationsTable);
        $venueSpaceId = $grammar->wrap('venue_space_id');
        $spaceId = $grammar->wrap('id');
        $spaceName = $grammar->wrap('name');
        $snapshot = $grammar->wrap('space_name_snapshot');

        DB::statement(sprintf(
            'UPDATE %s SET %s = (SELECT %s FROM %s WHERE %s = %s.%s) WHERE %s IS NULL AND %s IS NOT NULL',
            $wrappedLocationsTable,
            $snapshot,
            $spaceName,
            $wrappedTable,
            $spaceId,
            $wrappedLocationsTable,
            $venueSpaceId,
            $snapshot,
            $venueSpaceId,
        ));

        $oldIndex = $grammar->wrap($venueSpacesTable . '_slug_unique');
        $catalogIndex = $grammar->wrap($venueSpacesTable . '_catalog_slug_unique');
        $venueIndex = $grammar->wrap($venueSpacesTable . '_venue_slug_unique');

        DB::statement(sprintf('DROP INDEX IF EXISTS %s', $oldIndex));
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', $catalogIndex));
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', $venueIndex));

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
