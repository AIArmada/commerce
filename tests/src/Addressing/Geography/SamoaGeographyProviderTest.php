<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Samoa\SamoaAddressFormatter;
use AIArmada\Addressing\Geography\Samoa\SamoaGeographyProvider;

it('formats Samoan addresses with the code right of the locality', function (): void {
    $formatted = app(SamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Salenesa Street Motootua',
        'city' => 'Apia',
        'postcode' => 'WS1330',
        'country_code' => 'WS',
    ]));

    expect($formatted)->toBe("Salenesa Street Motootua\nApia WS1330\nSamoa");
});

it('formats Samoan PO box addresses with the district code', function (): void {
    $formatted = app(SamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Apia',
        'postcode' => 'WS1330',
        'country_code' => 'WS',
    ]));

    expect($formatted)->toBe("PO Box 1\nApia WS1330\nSamoa");
});

it('uses okina glottals and spells Gagaifomauga without one', function (): void {
    $areas = app(SamoaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ws:district:aana')->name)->toBe('Aʻana')
        ->and($areas->get('ws:district:faasaleleaga')->name)->toBe('Faʻasaleleaga')
        ->and($areas->get('ws:district:gagaemauga')->name)->toBe('Gagaʻemauga')
        ->and($areas->get('ws:district:gagaifomauga')->name)->toBe('Gagaifomauga')
        ->and($areas->get('ws:district:satupaitea')->name)->toBe('Satupaʻitea')
        ->and($areas->get('ws:district:vaa-o-fonoti')->name)->toBe('Vaʻa-o-Fonoti');
});
