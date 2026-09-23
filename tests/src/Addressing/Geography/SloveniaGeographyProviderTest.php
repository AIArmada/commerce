<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Slovenia\SloveniaAddressFormatter;
use AIArmada\Addressing\Geography\Slovenia\SloveniaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Slovenian addresses with the postcode left of the locality', function (): void {
    $formatted = app(SloveniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Prešemova ul. 16',
        'city' => 'KRANJ',
        'postcode' => '4000',
        'country_code' => 'SI',
    ]));

    expect($formatted)->toBe("Prešemova ul. 16\n4000 KRANJ\nSlovenia");
});
it('formats Slovenian addresses passing SI prefixes through', function (): void {
    $formatted = app(SloveniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Slovenska cesta 1',
        'city' => 'LJUBLJANA',
        'postcode' => 'SI-1000',
        'country_code' => 'SI',
    ]));

    expect($formatted)->toBe("Slovenska cesta 1\nSI-1000 LJUBLJANA\nSlovenia");
});
it('exposes corrected Slovenian municipality names', function (): void {
    $areas = app(SloveniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('si:municipality:dobrovapolhov-gradec')->name)->toBe('Dobrova-Polhov Gradec')
        ->and($areas->get('si:municipality:dobrovapolhov-gradec')->code)->toBe('021')
        ->and($areas->get('si:municipality:miklavz-na-dravskem-polju')->name)->toBe('Miklavž na Dravskem polju')
        ->and($areas->get('si:municipality:miklavz-na-dravskem-polju')->code)->toBe('169')
        ->and($areas->get('si:municipality:sveti-jurij-v-slovenskih-goricah')->name)->toBe('Sveti Jurij v Slovenskih goricah')
        ->and($areas->get('si:municipality:sveti-jurij-v-slovenskih-goricah')->type)->toBe('municipality');
});

it('ships 200 municipalities plus 12 urban municipalities as terminal states', function (): void {
    $areas = app(SloveniaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(212)
        ->and($areas->where('type', 'municipality'))->toHaveCount(200)
        ->and($areas->where('type', 'urban_municipality'))->toHaveCount(12)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue();
});

it('roles urban municipalities with the municipality selector', function (): void {
    $roles = app(SloveniaGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['si:urban_municipality:celje'][0]['role'])->toBe('municipality');
});

it('labels tiers Občina and Mestna občina', function (): void {
    $provider = app(SloveniaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['municipality' => 'Občina', 'urban_municipality' => 'Mestna občina'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
