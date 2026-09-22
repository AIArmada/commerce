<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guyana\GuyanaAddressFormatter;
use AIArmada\Addressing\Geography\Guyana\GuyanaGeographyProvider;

it('formats Guyanese addresses with the postcode below the locality', function (): void {
    $formatted = app(GuyanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Room 15',
        'line2' => '183/185 Marja Bulding',
        'line3' => 'Lacytown',
        'city' => 'Georgetown',
        'postcode' => '4130106',
        'country_code' => 'GY',
    ]));

    expect($formatted)->toBe("Room 15\n183/185 Marja Bulding\nLacytown\nGeorgetown\n4130106\nGuyana");
});
it('formats Guyanese East Coast addresses with the district postcode', function (): void {
    $formatted = app(GuyanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lot 12 Public Road',
        'city' => 'East Coast Demerara',
        'postcode' => '4212501',
        'country_code' => 'GY',
    ]));

    expect($formatted)->toBe("Lot 12 Public Road\nEast Coast Demerara\n4212501\nGuyana");
});

it('ships 76 towns/councils under regions with parent links', function (): void {
    $areas = app(GuyanaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['town', 'neighbourhood_democratic_council']);

    expect($l2)->toHaveCount(76)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('gy:town:georgetown')->name)->toBe('Georgetown')
        ->and($byId->get('gy:town:linden')->name)->toBe('Linden')
        ->and($byId->get('gy:neighbourhood_democratic_council:wakenaam')->name)->toBe('Wakenaam');
});
