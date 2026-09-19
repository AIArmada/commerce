<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Lesotho\LesothoAddressFormatter;
use AIArmada\Addressing\Geography\Lesotho\LesothoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LesothoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Basotho tree with state links', function (): void {
    $this->seedCountry('LS');

    $result = app(SeedCountryGeographiesAction::class)->execute('LS');
    $country = AddressCountry::query()->where('iso2', 'LS')->firstOrFail();

    expect($result['seeded'])->toContain('LS')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(10);
});

it('formats Basotho addresses with the postcode right of the locality', function (): void {
    $formatted = app(LesothoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 500',
        'city' => 'MASERU',
        'postcode' => '100',
        'country_code' => 'LS',
    ]));

    expect($formatted)->toBe("P.O. Box 500\nMASERU 100\nLesotho");
});
it('prints matching Basotho city and district once when the postcode is missing', function (): void {
    $formatted = app(LesothoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 500',
        'city' => 'Maseru',
        'state' => 'Maseru',
        'country_code' => 'LS',
    ]));

    expect($formatted)->toBe("P.O. Box 500\nMaseru\nLesotho");
});
