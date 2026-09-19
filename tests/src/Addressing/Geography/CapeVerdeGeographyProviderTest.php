<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeAddressFormatter;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CapeVerdeGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Cape Verdean tree with state links', function (): void {
    $this->seedCountry('CV');

    $result = app(SeedCountryGeographiesAction::class)->execute('CV');
    $country = AddressCountry::query()->where('iso2', 'CV')->firstOrFail();

    expect($result['seeded'])->toContain('CV')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(24)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(24)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'geographical_region')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(24);
});

it('formats Cape Verdean addresses with the postcode left of the locality', function (): void {
    $formatted = app(CapeVerdeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 5 de Julho 138/Platô',
        'line2' => 'C.P. 38',
        'city' => 'PRAIA',
        'postcode' => '7600',
        'country_code' => 'CV',
    ]));

    expect($formatted)->toBe("Rua 5 de Julho 138/Platô\nC.P. 38\n7600 PRAIA\nCape Verde");
});
it('formats Cape Verdean addresses passing 7-digit codes through', function (): void {
    $formatted = app(CapeVerdeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 5 de Julho 138/Platô',
        'city' => 'PRAIA',
        'postcode' => '7600-120',
        'country_code' => 'CV',
    ]));

    expect($formatted)->toBe("Rua 5 de Julho 138/Platô\n7600-120 PRAIA\nCape Verde");
});
