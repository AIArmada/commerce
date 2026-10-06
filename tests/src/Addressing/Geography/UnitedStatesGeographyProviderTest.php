<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

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

it('pins the B21 verify-only pass: 40977 ZIPs, zero orphans, zero moves', function (): void {
    $dir = __DIR__.'/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('US', $dir.'/united-states-postal-codes.csv', $dir.'/united-states-postal-code-areas.csv', 'aiarmada.addressing.united-states');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(40977)
        ->and($postcodes)->toHaveCount(40977);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Anchors: NYC, Beverly Hills, Anchorage, DC design, CT planning region.
    expect($primary('00501'))->toBe('us:county:36103')
        ->and($primary('90210'))->toBe('us:county:06037')
        ->and($primary('99501'))->toBe('us:municipality:02020')
        ->and($primary('20001'))->toBe('us:district:district-of-columbia')
        ->and($primary('06001'))->toBe('us:planning_region:09110')
        ->and($primary('88134'))->toBe('us:county:35011')
        // Scope: no territory, military, or MH ZIPs bundled.
        ->and($byCode->has('00601'))->toBeFalse()
        ->and($byCode->has('96960'))->toBeFalse()
        ->and($byCode->has('09001'))->toBeFalse();
});
