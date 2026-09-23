<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicAddressFormatter;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicGeographyProvider;

it('formats Czech addresses with the spaced postcode left of the locality', function (): void {
    $formatted = app(CzechRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Hrušovská 455/10',
        'city' => 'Praha 102',
        'postcode' => '102 00',
        'country_code' => 'CZ',
    ]));

    expect($formatted)->toBe("Hrušovská 455/10\n102 00 Praha 102\nCzech Republic");
});
it('formats Czech rural addresses with the region below the postcode line', function (): void {
    $formatted = app(CzechRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Roprachtice 129',
        'city' => 'Roprachtice',
        'state' => 'Liberecký kraj',
        'postcode' => '513 01',
        'country_code' => 'CZ',
    ]));

    expect($formatted)->toBe("Roprachtice 129\n513 01 Roprachtice\nLiberecký kraj\nCzech Republic");
});

it('ships 76 districts under regions with parent links', function (): void {
    $areas = app(CzechRepublicGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(76)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('cz:district:benesov')->name)->toBe('Benešov')
        ->and($byId->get('cz:district:beroun')->name)->toBe('Beroun');
});

it('labels tiers Kraj, Hlavní Město and Okres', function (): void {
    $provider = app(CzechRepublicGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Kraj', 'capital_city' => 'Hlavní Město', 'district' => 'Okres'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
