<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Hungary\HungaryGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

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

it('links 3066 postcodes with the B16 single-leg fixes and fills', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('HU', $dir . '/hungary-postal-codes.csv', $dir . '/hungary-postal-code-areas.csv', 'aiarmada.addressing.hungary');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(3066);

    $byCode = $postcodes->groupBy->code;

    // FIX-1: Tárkány is 2945/Kisbéri (hu.wiki + OSM); 2943 is Bábolna-only.
    expect($byCode->get('2943')->map->areaSourceId->values()->all())->toBe(['hu:district:komarom']);

    // FIX-2: Meggyeskovácsi is 9757/Sárvári; 9764 is Csempeszkopács-only.
    expect($byCode->get('9764')->map->areaSourceId->values()->all())->toBe(['hu:district:szombathely'])
        ->and($byCode->get('9764')->where('isPrimary', true)->first()->areaSourceId)->toBe('hu:district:szombathely');

    // GN-only codes held out (hu.wiki contradicts both).
    foreach (['2242', '3071'] as $bad) {
        expect($byCode->has($bad))->toBeFalse();
    }

    $primary = fn (string $code): string => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Fills: 3244 Parádfürdő, 3603 Sajóvárkony, 9719 Szentkirály.
    expect($primary('3244'))->toBe('hu:district:petervasara')
        ->and($primary('3603'))->toBe('hu:district:ozd')
        ->and($primary('9719'))->toBe('hu:district:szombathely')
        ->and($primary('2945'))->toBe('hu:district:kisber')
        ->and($primary('9757'))->toBe('hu:district:sarvar');
});
