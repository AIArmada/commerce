<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Ukraine\UkraineAddressFormatter;
use AIArmada\Addressing\Geography\Ukraine\UkraineGeographyProvider;

it('formats Ukrainian addresses with locality, oblast and postcode lines', function (): void {
    $formatted = app(UkraineAddressFormatter::class)->format(AddressData::from([
        'line1' => 'vul. Khreshchatyk, 22',
        'city' => 'KYIV',
        'postcode' => '01055',
        'country_code' => 'UA',
    ]));

    expect($formatted)->toBe("vul. Khreshchatyk, 22\nKYIV\n01055\nUkraine");
});

it('ships 136 raions under oblasts with parent links', function (): void {
    $areas = app(UkraineGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'raion');

    expect($l2)->toHaveCount(136)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ua:raion:kharkiv')->name)->toBe('Kharkiv')
        ->and($byId->get('ua:raion:lviv')->name)->toBe('Lviv')
        ->and($byId->get('ua:raion:odesa')->name)->toBe('Odesa');
});
