<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bulgaria\BulgariaAddressFormatter;
use AIArmada\Addressing\Geography\Bulgaria\BulgariaGeographyProvider;

it('formats Bulgarian addresses with the postcode left of the locality', function (): void {
    $formatted = app(BulgariaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Aleksandur Ekzarkh 2',
        'city' => 'PLOVDIV',
        'postcode' => '4000',
        'country_code' => 'BG',
    ]));

    expect($formatted)->toBe("ul. Aleksandur Ekzarkh 2\n4000 PLOVDIV\nBulgaria");
});
it('formats Bulgarian rural addresses with the province below the postcode line', function (): void {
    $formatted = app(BulgariaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Trifon Georgiev 1',
        'city' => 'Mechka',
        'state' => 'Ruse',
        'postcode' => '3264',
        'country_code' => 'BG',
    ]));

    expect($formatted)->toBe("ul. Trifon Georgiev 1\n3264 Mechka\nRuse\nBulgaria");
});

it('ships 265 municipalitys under districts with parent links', function (): void {
    $areas = app(BulgariaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(265)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('bg:municipality:sofia')->name)->toBe('Sofia')
        ->and($byId->get('bg:municipality:plovdiv')->name)->toBe('Plovdiv')
        ->and($byId->get('bg:municipality:burgas')->name)->toBe('Burgas');
});

it('labels districts Oblast', function (): void {
    $provider = app(BulgariaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['district' => 'Oblast'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
