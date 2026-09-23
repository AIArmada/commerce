<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesAddressFormatter;
use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats American addresses with the state abbreviation and ZIP', function (): void {
    $formatted = app(UnitedStatesAddressFormatter::class)->format(AddressData::from([
        'line1' => '123 MAGNOLIA ST',
        'city' => 'HEMPSTEAD',
        'state' => 'New York',
        'postcode' => '11550-1234',
        'country_code' => 'US',
    ]));

    expect($formatted)->toBe("123 MAGNOLIA ST\nHEMPSTEAD NY 11550-1234\nUnited States");
});

it('ships 3,143 counties and county equivalents under their states', function (): void {
    $areas = app(UnitedStatesGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(3199)
        ->and($areas->where('level', 1))->toHaveCount(56)
        ->and($areas->where('level', 2))->toHaveCount(3143)
        ->and($areas->where('type', 'county'))->toHaveCount(2999)
        ->and($areas->where('type', 'parish'))->toHaveCount(64)
        ->and($areas->where('type', 'city'))->toHaveCount(41)
        ->and($areas->where('type', 'planning_region'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'us:state:texas'))->toHaveCount(254)
        ->and($areas->where('parentSourceId', 'us:state:virginia'))->toHaveCount(133);

    $byId = $areas->keyBy->sourceId;

    expect($byId->get('us:planning_region:09110')->name)->toBe('Capitol')
        ->and($byId->get('us:city:51760')->name)->toBe('Richmond')
        ->and($byId->get('us:borough:02110')->name)->toBe('Juneau')
        ->and($byId->get('us:municipality:02020')->name)->toBe('Anchorage')
        ->and($byId->has('us:county:11001'))->toBeFalse()
        ->and($areas->where('parentSourceId', 'us:territory:puerto-rico'))->toBeEmpty();
});

it('declares USPS abbreviations for all 56 states, territories, and DC', function (): void {
    $names = app(UnitedStatesGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names)->toHaveCount(56)
        ->and($names['us:state:california'][0])->toBe(['name' => 'CA', 'name_type' => 'abbreviation'])
        ->and($names['us:state:texas'][0]['name'])->toBe('TX')
        ->and($names['us:district:district-of-columbia'][0]['name'])->toBe('DC')
        ->and($names['us:territory:puerto-rico'][0]['name'])->toBe('PR')
        ->and(collect($names)->flatten(1)->pluck('name')->all())->not->toContain('AA', 'AE', 'AP');
});
