<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropColumns(
            (string) config('events.database.tables.venues', 'venues'),
            [
                'line1',
                'line2',
                'line3',
                'city',
                'state',
                'postcode',
                'country',
                'country_code',
                'latitude',
                'longitude',
                'google_place_id',
                'google_maps_url',
                'waze_url',
                'map_url',
                'directions',
                'geocoded_at',
                'geocoding_source',
            ],
        );

        $this->dropColumns(
            (string) config('events.database.tables.event_locations', 'event_locations'),
            [
                'line1',
                'line2',
                'line3',
                'city',
                'state',
                'postcode',
                'country',
                'country_code',
                'latitude',
                'longitude',
                'google_place_id',
                'google_maps_url',
                'waze_url',
                'map_url',
                'directions',
                'address_snapshot',
                'geocoded_at',
                'geocoding_source',
            ],
        );
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function dropColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existingColumns = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn($tableName, $column),
        ));

        if ($existingColumns === []) {
            return;
        }

        $indexes = array_values(array_filter(
            Schema::getIndexes($tableName),
            static fn (array $index): bool => ! $index['primary']
                && array_intersect($index['columns'], $existingColumns) !== [],
        ));

        if ($indexes !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes): void {
                foreach ($indexes as $index) {
                    $table->dropIndex($index['name']);
                }
            });
        }

        Schema::table($tableName, function (Blueprint $table) use ($existingColumns): void {
            $table->dropColumn($existingColumns);
        });
    }
};
