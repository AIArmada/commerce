<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Finland\FinlandAddressFormatter;
use AIArmada\Addressing\Geography\Finland\FinlandGeographyProvider;

it('formats Finnish addresses with the postcode left of the locality', function (): void {
    $formatted = app(FinlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Mäkelänkatu 25 B 13',
        'city' => 'HELSINKI',
        'postcode' => '00550',
        'country_code' => 'FI',
    ]));

    expect($formatted)->toBe("Mäkelänkatu 25 B 13\n00550 HELSINKI\nFinland");
});
it('formats Finnish post box addresses with the box postcode', function (): void {
    $formatted = app(FinlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PL 900',
        'city' => 'HELSINKI',
        'postcode' => '00101',
        'country_code' => 'FI',
    ]));

    expect($formatted)->toBe("PL 900\n00101 HELSINKI\nFinland");
});

it('ships 292 municipalities/cities under regions with parent links', function (): void {
    $areas = app(FinlandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['municipality', 'city']);

    expect($l2)->toHaveCount(292)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('fi:city:helsinki')->name)->toBe('Helsinki')
        ->and($byId->get('fi:city:espoo')->name)->toBe('Espoo')
        ->and($byId->get('fi:city:tampere')->name)->toBe('Tampere');
});
