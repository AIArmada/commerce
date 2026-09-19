<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Denmark\DenmarkAddressFormatter;
use AIArmada\Addressing\Geography\Denmark\DenmarkGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(DenmarkGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Danish tree with state links', function (): void {
    $this->seedCountry('DK');

    $result = app(SeedCountryGeographiesAction::class)->execute('DK');
    $country = AddressCountry::query()->where('iso2', 'DK')->firstOrFail();

    expect($result['seeded'])->toContain('DK')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(5)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});

it('formats Danish addresses with the postcode left of the locality', function (): void {
    $formatted = app(DenmarkAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Kastanievej 15, 2, Agerskov',
        'city' => 'SKANDERBORG',
        'postcode' => '8660',
        'country_code' => 'DK',
    ]));

    expect($formatted)->toBe("Kastanievej 15, 2, Agerskov\n8660 SKANDERBORG\nDenmark");
});
it('formats Danish post box addresses with the bare postcode', function (): void {
    $formatted = app(DenmarkAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Postboks 321',
        'city' => 'SKANDERBORG',
        'postcode' => '8660',
        'country_code' => 'DK',
    ]));

    expect($formatted)->toBe("Postboks 321\n8660 SKANDERBORG\nDenmark");
});
