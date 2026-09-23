<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guernsey\GuernseyAddressFormatter;
use AIArmada\Addressing\Geography\Guernsey\GuernseyGeographyProvider;

it('formats Guernsey addresses with the postcode below the post town', function (): void {
    $formatted = app(GuernseyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Anybank House',
        'line2' => 'Le Pollet',
        'line3' => 'St Peter Port',
        'city' => 'GUERNSEY',
        'postcode' => 'GY1 1AA',
        'country_code' => 'GG',
    ]));

    expect($formatted)->toBe("Anybank House\nLe Pollet\nSt Peter Port\nGUERNSEY\nGY1 1AA\nGuernsey");
});
it('formats Sark addresses with the two-digit district postcode', function (): void {
    $formatted = app(GuernseyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'La Seigneurie',
        'city' => 'SARK',
        'postcode' => 'GY10 1SF',
        'country_code' => 'GG',
    ]));

    expect($formatted)->toBe("La Seigneurie\nSARK\nGY10 1SF\nGuernsey");
});

it('ships 10 parishes plus Alderney and Sark as dependencies', function (): void {
    $areas = app(GuernseyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(10)
        ->and($areas->where('type', 'dependency'))->toHaveCount(2)
        ->and($byId->get('gg:dependency:alderney')->name)->toBe('Alderney')
        ->and($byId->get('gg:dependency:sark')->name)->toBe('Sark');
});
