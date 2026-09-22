<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Peru\PeruAddressFormatter;
use AIArmada\Addressing\Geography\Peru\PeruGeographyProvider;

it('formats Peruvian addresses with the postcode above the province', function (): void {
    $formatted = app(PeruAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jr. Jorge Salazar Araoz. N° 171',
        'state' => 'LIMA',
        'postcode' => '15074',
        'country_code' => 'PE',
    ]));

    expect($formatted)->toBe("Jr. Jorge Salazar Araoz. N° 171\n15074\nLIMA\nPeru");
});

it('ships 196 provinces under regions with parent links', function (): void {
    $areas = app(PeruGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'province');

    expect($l2)->toHaveCount(196)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('pe:province:lima')->name)->toBe('Lima')
        ->and($byId->get('pe:province:callao')->name)->toBe('Callao')
        ->and($byId->get('pe:province:cusco')->name)->toBe('Cusco');
});
