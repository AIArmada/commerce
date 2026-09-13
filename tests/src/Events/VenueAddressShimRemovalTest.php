<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

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

it('folds the legacy venue address drop into the creates', function (): void {
    $repositoryRoot = dirname(__DIR__, 3);
    $venueCreate = (string) file_get_contents($repositoryRoot . '/packages/events/database/migrations/2000_01_01_000004_create_event_venues_table.php');
    $locationCreate = (string) file_get_contents($repositoryRoot . '/packages/events/database/migrations/2000_01_01_000007_create_event_locations_table.php');

    foreach (['line1', 'line2', 'google_place_id', 'google_maps_url', 'waze_url', 'map_url', 'directions', 'geocoded_at', 'geocoding_source'] as $column) {
        expect($venueCreate)->not->toContain("'{$column}'")
            ->and($locationCreate)->not->toContain("'{$column}'");
    }

    expect($locationCreate)->not->toContain("'address_snapshot'");
});
