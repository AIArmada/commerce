<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Canada\CanadaAddressFormatter;
use AIArmada\Addressing\Geography\Canada\CanadaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Canadian addresses with the province abbreviation and postcode', function (): void {
    $formatted = app(CanadaAddressFormatter::class)->format(AddressData::from([
        'line1' => '8450 Newman Blvd.',
        'city' => 'MONTREAL',
        'state' => 'Quebec',
        'postcode' => 'h3z 2y7',
        'country_code' => 'CA',
    ]));

    expect($formatted)->toBe("8450 Newman Blvd.\nMONTREAL QC H3Z 2Y7\nCanada");
});

it('ships 5,028 census subdivisions under their provinces', function (): void {
    $areas = app(CanadaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(5041)
        ->and($areas->where('level', 1))->toHaveCount(13)
        ->and($areas->where('level', 2))->toHaveCount(5028)
        ->and($areas->where('type', 'municipality'))->toHaveCount(3770)
        ->and($areas->where('type', 'indigenous_reserve'))->toHaveCount(1028)
        ->and($areas->where('type', 'unorganized'))->toHaveCount(230)
        ->and($areas->where('parentSourceId', 'ca:province:quebec'))->toHaveCount(1279)
        ->and($areas->where('parentSourceId', 'ca:province:new-brunswick'))->toHaveCount(109);

    $byId = $areas->keyBy->sourceId;

    expect($byId->get('ca:municipality:3520005')->name)->toBe('Toronto')
        ->and($byId->get('ca:municipality:5915022')->name)->toBe('Vancouver')
        ->and($byId->get('ca:unorganized:1001101')->name)->toBe('Division No.  1, Subd. V');
});

it('declares postal abbreviations for all 13 provinces and territories', function (): void {
    $names = app(CanadaGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names)->toHaveCount(13)
        ->and($names['ca:province:ontario'][0])->toBe(['name' => 'ON', 'name_type' => 'abbreviation'])
        ->and($names['ca:province:quebec'][0]['name'])->toBe('QC')
        ->and($names['ca:province:quebec'][1])->toBe(['name' => 'Québec', 'name_type' => 'alternative'])
        ->and($names['ca:territory:nunavut'][0]['name'])->toBe('NU');
});
