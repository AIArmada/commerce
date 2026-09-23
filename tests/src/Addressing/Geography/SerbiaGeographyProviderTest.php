<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Serbia\SerbiaAddressFormatter;
use AIArmada\Addressing\Geography\Serbia\SerbiaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Serbian addresses with the postcode left of the office', function (): void {
    $formatted = app(SerbiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Beogradska 3',
        'city' => 'BAJMOK',
        'postcode' => '24210',
        'country_code' => 'RS',
    ]));

    expect($formatted)->toBe("Beogradska 3\n24210 BAJMOK\nSerbia");
});
it('prints matching Serbian city and capital once', function (): void {
    $formatted = app(SerbiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Knez Mihailova 10',
        'city' => 'Belgrade',
        'state' => 'Belgrade',
        'postcode' => '11130',
        'country_code' => 'RS',
    ]));

    expect($formatted)->toBe("Knez Mihailova 10\n11130 Belgrade\nSerbia");
});

it('ships 157 municipalities/cities under districts with parent links', function (): void {
    $areas = app(SerbiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['municipality', 'city', 'city_municipality'])->where('level', 2);

    expect($l2)->toHaveCount(157)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('rs:city:novi-sad')->name)->toBe('Novi Sad')
        ->and($byId->get('rs:city_municipality:zemun')->name)->toBe('Zemun')
        ->and($byId->get('rs:city_municipality:novi-beograd')->name)->toBe('Novi Beograd');
});

it('roles Belgrade as a city and county cities as municipalities', function (): void {
    $roles = app(SerbiaGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['rs:city:belgrade'][0]['role'])->toBe('city')
        ->and($roles['rs:city:bor'][0]['role'])->toBe('municipality');
});

it('labels tiers Okrug, Pokrajina, Grad, Opština and Gradska opština', function (): void {
    $provider = app(SerbiaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['district' => 'Okrug', 'province' => 'Pokrajina', 'city' => 'Grad', 'municipality' => 'Opština', 'city_municipality' => 'Gradska opština'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
