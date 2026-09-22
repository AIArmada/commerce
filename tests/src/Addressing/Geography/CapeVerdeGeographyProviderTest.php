<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeAddressFormatter;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeGeographyProvider;

it('formats Cape Verdean addresses with the postcode left of the locality', function (): void {
    $formatted = app(CapeVerdeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 5 de Julho 138/Platô',
        'line2' => 'C.P. 38',
        'city' => 'PRAIA',
        'postcode' => '7600',
        'country_code' => 'CV',
    ]));

    expect($formatted)->toBe("Rua 5 de Julho 138/Platô\nC.P. 38\n7600 PRAIA\nCape Verde");
});
it('formats Cape Verdean addresses passing 7-digit codes through', function (): void {
    $formatted = app(CapeVerdeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 5 de Julho 138/Platô',
        'city' => 'PRAIA',
        'postcode' => '7600-120',
        'country_code' => 'CV',
    ]));

    expect($formatted)->toBe("Rua 5 de Julho 138/Platô\n7600-120 PRAIA\nCape Verde");
});

it('ships 32 parishes under municipalities with parent links', function (): void {
    $areas = app(CapeVerdeGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'parish');

    expect($l2)->toHaveCount(32)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('cv:parish:santo-amaro-abade')->name)->toBe('Santo Amaro Abade')
        ->and($byId->get('cv:parish:maio:nossa-senhora-da-luz')->name)->toBe('Nossa Senhora da Luz');
});
