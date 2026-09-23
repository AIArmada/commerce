<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tuvalu\TuvaluAddressFormatter;
use AIArmada\Addressing\Geography\Tuvalu\TuvaluGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Tuvaluan addresses without a postcode system', function (): void {
    $formatted = app(TuvaluAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Funafuti',
        'country_code' => 'TV',
    ]));

    expect($formatted)->toBe("PO Box 1\nFunafuti\nTuvalu");
});

it('prints any supplied Funafuti code on its own line', function (): void {
    $formatted = app(TuvaluAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Funafuti',
        'postcode' => '99999',
        'country_code' => 'TV',
    ]));

    expect($formatted)->toBe("PO Box 1\nFunafuti\n99999\nTuvalu");
});

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
