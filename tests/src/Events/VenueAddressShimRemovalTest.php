<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('keeps the legacy venue address shim and flat readers out of owned source and tests', function (): void {
    $repositoryRoot = dirname(__DIR__, 3);
    $roots = [
        $repositoryRoot . '/packages/events/src',
        $repositoryRoot . '/packages/filament-events/src',
        $repositoryRoot . '/tests/src/Events',
        $repositoryRoot . '/tests/src/FilamentEvents',
    ];
    $legacyPatterns = [
        'ReadsLegacy' . 'AddressColumns',
        'getPrimary' . 'AddressData',
        'buildAddressDataFromFlatColumns',
        "getAttribute('line1')",
        "getAttribute('line2')",
        "getAttribute('line3')",
        "getAttribute('country_code')",
        "getAttribute('google_maps_url')",
        "getAttribute('map_url')",
        "getAttribute('google_place_id')",
        "getAttribute('waze_url')",
        "getAttribute('directions')",
    ];
    $references = [];

    foreach ($roots as $root) {
        foreach (File::allFiles($root) as $file) {
            if ($file->getPathname() === __FILE__) {
                continue;
            }

            $contents = File::get($file->getPathname());

            foreach ($legacyPatterns as $legacyPattern) {
                if (str_contains($contents, $legacyPattern)) {
                    $references[] = $file->getPathname() . ' contains ' . $legacyPattern;
                }
            }
        }
    }

    expect($references)->toBeEmpty();
});

it('guards the legacy venue address column drop and makes it re-runnable', function (): void {
    $migrationPath = dirname(__DIR__, 3) . '/packages/events/database/migrations/2026_09_12_000002_drop_legacy_venue_address_columns.php';
    $venueTable = 'events_shim_venues_' . Str::lower(Str::random(8));
    $locationTable = 'events_shim_locations_' . Str::lower(Str::random(8));
    $originalVenueTable = config('events.database.tables.venues');
    $originalLocationTable = config('events.database.tables.event_locations');

    try {
        config()->set('events.database.tables.venues', $venueTable);
        config()->set('events.database.tables.event_locations', $locationTable);

        Schema::create($venueTable, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('line1');
            $table->string('city')->index();
            $table->string('country_code')->index();
            $table->string('google_place_id')->index();
            $table->string('keep_me');
        });

        Schema::create($locationTable, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('line1');
            $table->string('city')->index();
            $table->string('country_code')->index();
            $table->string('google_place_id')->index();
            $table->json('address_snapshot')->nullable();
            $table->string('keep_me');
        });

        $migration = require $migrationPath;
        $migration->up();
        $migration->up();

        config()->set('events.database.tables.venues', $venueTable . '_missing');
        config()->set('events.database.tables.event_locations', $locationTable . '_missing');
        $migration->up();

        expect(Schema::hasColumn($venueTable, 'line1'))->toBeFalse()
            ->and(Schema::hasColumn($venueTable, 'city'))->toBeFalse()
            ->and(Schema::hasColumn($venueTable, 'country_code'))->toBeFalse()
            ->and(Schema::hasColumn($venueTable, 'google_place_id'))->toBeFalse()
            ->and(Schema::hasColumn($venueTable, 'keep_me'))->toBeTrue()
            ->and(Schema::hasColumn($locationTable, 'line1'))->toBeFalse()
            ->and(Schema::hasColumn($locationTable, 'city'))->toBeFalse()
            ->and(Schema::hasColumn($locationTable, 'country_code'))->toBeFalse()
            ->and(Schema::hasColumn($locationTable, 'google_place_id'))->toBeFalse()
            ->and(Schema::hasColumn($locationTable, 'address_snapshot'))->toBeFalse()
            ->and(Schema::hasColumn($locationTable, 'keep_me'))->toBeTrue();
    } finally {
        Schema::dropIfExists($venueTable);
        Schema::dropIfExists($locationTable);
        config()->set('events.database.tables.venues', $originalVenueTable);
        config()->set('events.database.tables.event_locations', $originalLocationTable);
    }
});
