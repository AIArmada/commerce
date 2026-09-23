<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Grenada\GrenadaAddressFormatter;
use AIArmada\Addressing\Geography\Grenada\GrenadaGeographyProvider;

it('formats Grenadian addresses without a postcode system', function (): void {
    $formatted = app(GrenadaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Woburn',
        'city' => "ST. GEORGE'S",
        'country_code' => 'GD',
    ]));

    expect($formatted)->toBe("Woburn\nST. GEORGE'S\nGrenada");
});
it('formats Grenadian box addresses with the municipality only', function (): void {
    $formatted = app(GrenadaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. BOX 1234',
        'city' => "ST. GEORGE'S",
        'country_code' => 'GD',
    ]));

    expect($formatted)->toBe("P.O. BOX 1234\nST. GEORGE'S\nGrenada");
});

it('ships 6 parishes plus Carriacou as a dependency', function (): void {
    $areas = app(GrenadaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(6)
        ->and($areas->where('type', 'dependency'))->toHaveCount(1)
        ->and($byId->get('gd:dependency:carriacou')->name)->toBe('Carriacou');
});
