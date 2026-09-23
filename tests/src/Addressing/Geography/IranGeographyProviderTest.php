<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Iran\IranAddressFormatter;
use AIArmada\Addressing\Geography\Iran\IranGeographyProvider;

it('formats Iranian addresses with the postcode below the province', function (): void {
    $formatted = app(IranAddressFormatter::class)->format(AddressData::from([
        'line1' => 'West 196 street',
        'line2' => 'No. 12 third floor',
        'city' => 'Tehranpars',
        'state' => 'Tehran Province',
        'postcode' => '1619614153',
        'country_code' => 'IR',
    ]));

    expect($formatted)->toBe("West 196 street\nNo. 12 third floor\nTehranpars\nTehran Province\n1619614153\nIran");
});
it('formats Iranian addresses without a postcode line when missing', function (): void {
    $formatted = app(IranAddressFormatter::class)->format(AddressData::from([
        'line1' => 'West 196 street',
        'city' => 'Tehranpars',
        'state' => 'Tehran Province',
        'country_code' => 'IR',
    ]));

    expect($formatted)->toBe("West 196 street\nTehranpars\nTehran Province\nIran");
});
it('exposes the corrected West Azerbaijan province slug and name', function (): void {
    $areas = app(IranGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ir:province:west-azerbaijan')->name)->toBe('West Azerbaijan')
        ->and($areas->get('ir:province:west-azerbaijan')->code)->toBe('04')
        ->and($areas->get('ir:province:west-azerbaijan')->type)->toBe('province')
        ->and($areas->has('ir:province:west-azarbaijan'))->toBeFalse();
});

it('ships 429 May-2019 counties under provinces with parent links', function (): void {
    $areas = app(IranGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(429)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'ir:province:tehran'))->toHaveCount(16)
        ->and($l2->where('parentSourceId', 'ir:province:qom'))->toHaveCount(1)
        ->and($byId->get('ir:county:abadan')->parentSourceId)->toBe('ir:province:khuzestan');
});

it('labels tiers Ostan and Shahrestan', function (): void {
    $provider = app(IranGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Ostan', 'county' => 'Shahrestan'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
