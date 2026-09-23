<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\DominicanRepublic\DominicanRepublicAddressFormatter;
use AIArmada\Addressing\Geography\DominicanRepublic\DominicanRepublicGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Dominican Republic addresses with the postcode left of the locality', function (): void {
    $formatted = app(DominicanRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'C/45 # 33',
        'line2' => 'Katanga, Los Minas',
        'city' => 'SANTO DOMINGO',
        'postcode' => '11903',
        'country_code' => 'DO',
    ]));

    expect($formatted)->toBe("C/45 # 33\nKatanga, Los Minas\n11903 SANTO DOMINGO\nDominican Republic");
});
it('formats Dominican Republic Santiago addresses with the town postcode', function (): void {
    $formatted = app(DominicanRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle del Sol 12',
        'city' => 'SANTIAGO',
        'postcode' => '51000',
        'country_code' => 'DO',
    ]));

    expect($formatted)->toBe("Calle del Sol 12\n51000 SANTIAGO\nDominican Republic");
});

it('ships 31 provinces plus the Distrito Nacional under regions with parent links', function (): void {
    $areas = app(DominicanRepublicGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(32)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('do:province:azua')->name)->toBe('Azua')
        ->and($byId->get('do:district:distrito-nacional')->parentSourceId)->toBe('do:region:ozama');
});

it('labels tiers Región, Provincia, Distrito and Municipio', function (): void {
    $provider = app(DominicanRepublicGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Región', 'province' => 'Provincia', 'district' => 'Distrito', 'municipality' => 'Municipio'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});

it('ships 158 municipalities under their provinces with postal locality roles', function (): void {
    $provider = app(DominicanRepublicGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'municipality'))->toHaveCount(158)
        ->and($byId->get('do:municipality:santiago-de-los-caballeros')->parentSourceId)->toBe('do:province:santiago')
        ->and($byId->get('do:municipality:higuey')->parentSourceId)->toBe('do:province:la-altagracia')
        ->and($byId->get('do:municipality:baitoa')->parentSourceId)->toBe('do:province:santiago');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['do:municipality:santiago-de-los-caballeros'][0]['role'])->toBe('postal_locality');
});
