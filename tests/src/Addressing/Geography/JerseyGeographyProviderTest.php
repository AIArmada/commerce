<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Jersey\JerseyAddressFormatter;
use AIArmada\Addressing\Geography\Jersey\JerseyGeographyProvider;

it('formats Jersey addresses with the postcode below the post town', function (): void {
    $formatted = app(JerseyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Town View',
        'line2' => 'Stopford Road',
        'line3' => 'St Helier',
        'city' => 'JERSEY',
        'postcode' => 'JE2 4LB',
        'country_code' => 'JE',
    ]));

    expect($formatted)->toBe("Town View\nStopford Road\nSt Helier\nJERSEY\nJE2 4LB\nJersey");
});
it('formats Jersey town addresses with the parish postcode', function (): void {
    $formatted = app(JerseyAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 Esplanade',
        'city' => 'St Helier',
        'postcode' => 'JE1 1AA',
        'country_code' => 'JE',
    ]));

    expect($formatted)->toBe("5 Esplanade\nSt Helier\nJE1 1AA\nJersey");
});

it('ships 56 vingtaines/cantons/cueillettes under parishes with parent links', function (): void {
    $areas = app(JerseyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['vingtaine', 'canton', 'cueillette']);

    expect($l2)->toHaveCount(56)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('je:vingtaine:la-grande-vingtaine')->name)->toBe('La Grande Vingtaine')
        ->and($byId->get('je:cueillette:la-grande-cueillette')->name)->toBe('La Grande Cueillette')
        ->and($byId->get('je:canton:canton-de-bas-de-la-vingtaine-de-la-ville')->name)->toBe('Canton de Bas de la Vingtaine de la Ville');
});
