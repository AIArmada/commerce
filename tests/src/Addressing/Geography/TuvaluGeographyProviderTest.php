<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Tuvalu\TuvaluGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('names the island council Niutao without a type suffix', function (): void {
    $areas = app(TuvaluGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('tv:island_council:niutao')->name)->toBe('Niutao')
        ->and($areas->get('tv:island_council:niutao')->code)->toBe('NIT')
        ->and($areas->has('tv:island_council:niutao-island-council'))->toBeFalse();
});

it('roles Funafuti town council with the island-council selector', function (): void {
    $roles = app(TuvaluGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['tv:town_council:funafuti'][0]['role'])->toBe('island_council');
});

it('pins the verified Tuvalu tree of 8 councils', function (): void {
    $areas = app(TuvaluGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'town_council'))->toHaveCount(1)
        ->and($areas->where('type', 'island_council'))->toHaveCount(7);

    // ISO 3166-2:TV codes; Niulakita has no code (administered
    // with Niutao). Nanumanga kept (GeoNames + en-wiki agree);
    // Nanumaga ships as the official alias (ISO + UPU form).
    expect($byId->get('tv:town_council:funafuti')->code)->toBe('FUN')
        ->and($byId->get('tv:island_council:nanumanga')->code)->toBe('NMG')
        ->and($byId->get('tv:island_council:nanumea')->code)->toBe('NMA')
        ->and($byId->get('tv:island_council:niutao')->code)->toBe('NIT')
        ->and($byId->get('tv:island_council:nui')->code)->toBe('NUI')
        ->and($byId->get('tv:island_council:nukufetau')->code)->toBe('NKF')
        ->and($byId->get('tv:island_council:nukulaelae')->code)->toBe('NKL')
        ->and($byId->get('tv:island_council:vaitupu')->code)->toBe('VAI');

    $names = app(TuvaluGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['tv:island_council:nanumanga'][0]['name'])->toBe('Nanumaga');
});
