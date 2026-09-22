<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Latvia\LatviaAddressFormatter;
use AIArmada\Addressing\Geography\Latvia\LatviaGeographyProvider;

it('formats Latvian addresses with the postcode right of the locality', function (): void {
    $formatted = app(LatviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Kr. Barona street 7, dz. 1',
        'city' => 'RIGA',
        'postcode' => 'LV-1050',
        'country_code' => 'LV',
    ]));

    expect($formatted)->toBe("Kr. Barona street 7, dz. 1\nRIGA, LV-1050\nLatvia");
});
it('formats Latvian sub-locality addresses with the office postcode', function (): void {
    $formatted = app(LatviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Valdemāra street 42, Ainaži',
        'city' => 'SALACGRIVAS NOV.',
        'postcode' => 'LV-4035',
        'country_code' => 'LV',
    ]));

    expect($formatted)->toBe("Valdemāra street 42, Ainaži\nSALACGRIVAS NOV., LV-4035\nLatvia");
});

it('ships 585 parishes, towns and cities under municipalities with parent links', function (): void {
    $areas = app(LatviaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(585)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('level', 1))->toHaveCount(42)
        ->and($byId->has('lv:municipality:varaklani'))->toBeFalse()
        ->and($l2->where('parentSourceId', 'lv:municipality:madona'))->toHaveCount(25)
        ->and($byId->get('lv:parish:marupe:sala-parish')->parentSourceId)->toBe('lv:municipality:marupe');
});
