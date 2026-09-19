<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Venezuela\VenezuelaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(VenezuelaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Venezuelan tree with state links', function (): void {
    $this->seedCountry('VE');

    $result = app(SeedCountryGeographiesAction::class)->execute('VE');
    $country = AddressCountry::query()->where('iso2', 'VE')->firstOrFail();

    expect($result['seeded'])->toContain('VE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(23)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_district')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'federal_dependency')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(25);
});
