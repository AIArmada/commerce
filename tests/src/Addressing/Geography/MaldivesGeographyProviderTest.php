<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Maldives\MaldivesAddressFormatter;
use AIArmada\Addressing\Geography\Maldives\MaldivesGeographyProvider;

it('formats Maldivian addresses with the postcode right of the locality', function (): void {
    $formatted = app(MaldivesAddressFormatter::class)->format(AddressData::from([
        'line1' => '26, BODUTHAKURUFAANU MAGU',
        'city' => 'MALÉ',
        'postcode' => '20026',
        'country_code' => 'MV',
    ]));

    expect($formatted)->toBe("26, BODUTHAKURUFAANU MAGU\nMALÉ 20026\nMaldives");
});
it('formats Maldivian island addresses with the atoll below the postcode line', function (): void {
    $formatted = app(MaldivesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Nirolhu Magu 8',
        'city' => 'Hulhumale',
        'state' => 'Kaafu',
        'postcode' => '23000',
        'country_code' => 'MV',
    ]));

    expect($formatted)->toBe("Nirolhu Magu 8\nHulhumale 23000\nKaafu\nMaldives");
});

it('ships 18 atolls and 5 cities with Malé typed as a city', function (): void {
    $areas = app(MaldivesGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(23)
        ->and($areas->where('type', 'atoll'))->toHaveCount(18)
        ->and($areas->where('type', 'city'))->toHaveCount(5)
        ->and($areas->get('mv:city:male')->code)->toBe('MLE')
        ->and($areas->get('mv:city:fuvahmulah')->code)->toBe('FVM')
        ->and($areas->get('mv:city:kulhudhuffushi')->code)->toBe('KUH')
        ->and($areas->get('mv:city:thinadhoo')->code)->toBe('THD')
        ->and($areas->has('mv:atoll:male'))->toBeFalse()
        ->and($areas->has('mv:atoll:gnaviyani'))->toBeFalse();
});
