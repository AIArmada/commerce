<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Hungary\HungaryGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('renames Csongrád County and types Zalaegerszeg as a city', function (): void {
    $areas = app(HungaryGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('hu:county:csongrad-csanad-county')->name)->toBe('Csongrád-Csanád County')
        ->and($areas->get('hu:city_with_county_rights:zalaegerszeg')->code)->toBe('ZE');

    $names = app(HungaryGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['hu:county:csongrad-csanad-county'][0]['name'])->toBe('Csongrád County');
});

it('ships 197 districts under counties with parent links', function (): void {
    $areas = app(HungaryGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(197)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('hu:district:kispest')->name)->toBe('Kispest')
        ->and($byId->get('hu:district:varkerulet')->name)->toBe('Várkerület')
        ->and($byId->get('hu:district:debrecen')->name)->toBe('Debrecen')
        ->and($byId->get('hu:district:ajka')->parentSourceId)->toBe('hu:county:veszprem-county');
});
