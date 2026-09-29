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
