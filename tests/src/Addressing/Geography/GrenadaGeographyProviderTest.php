<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Grenada\GrenadaAddressFormatter;
use AIArmada\Addressing\Geography\Grenada\GrenadaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GrenadaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('parish')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Grenadian tree with state links', function (): void {
    $this->seedCountry('GD');

    $result = app(SeedCountryGeographiesAction::class)->execute('GD');
    $country = AddressCountry::query()->where('iso2', 'GD')->firstOrFail();

    expect($result['seeded'])->toContain('GD')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'parish')->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'dependency')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);
});

it('formats Grenadian addresses without a postcode system', function (): void {
    $formatted = app(GrenadaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Woburn',
        'city' => "ST. GEORGE'S",
        'country_code' => 'GD',
    ]));

    expect($formatted)->toBe("Woburn\nST. GEORGE'S\nGrenada");
});
it('formats Grenadian box addresses with the municipality only', function (): void {
    $formatted = app(GrenadaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. BOX 1234',
        'city' => "ST. GEORGE'S",
        'country_code' => 'GD',
    ]));

    expect($formatted)->toBe("P.O. BOX 1234\nST. GEORGE'S\nGrenada");
});
