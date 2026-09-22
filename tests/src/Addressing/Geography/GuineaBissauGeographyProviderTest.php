<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauAddressFormatter;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauGeographyProvider;

it('formats Bissau-Guinean addresses with the postcode left of the locality', function (): void {
    $formatted = app(GuineaBissauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua Justino Lopes 12C',
        'city' => 'BISSAU',
        'postcode' => '1000',
        'country_code' => 'GW',
    ]));

    expect($formatted)->toBe("Rua Justino Lopes 12C\n1000 BISSAU\nGuinea-Bissau");
});
it('formats Bissau-Guinean addresses without a postcode when missing', function (): void {
    $formatted = app(GuineaBissauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua Justino Lopes 12C',
        'city' => 'BISSAU',
        'country_code' => 'GW',
    ]));

    expect($formatted)->toBe("Rua Justino Lopes 12C\nBISSAU\nGuinea-Bissau");
});

it('ships 38 sectors under provinces with parent links', function (): void {
    $areas = app(GuineaBissauGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'sector');

    expect($l2)->toHaveCount(38)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('gw:sector:bambadinca')->name)->toBe('Bambadinca')
        ->and($byId->get('gw:sector:gabu')->name)->toBe('Gabú')
        ->and($byId->get('gw:sector:uno')->name)->toBe('Uno');
});
