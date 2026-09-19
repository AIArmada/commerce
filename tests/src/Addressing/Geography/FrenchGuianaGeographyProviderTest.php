<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\FrenchGuiana\FrenchGuianaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(FrenchGuianaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('overseas_region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Guianese tree with state links', function (): void {
    $this->seedCountry('GF');

    $result = app(SeedCountryGeographiesAction::class)->execute('GF');
    $country = AddressCountry::query()->where('iso2', 'GF')->firstOrFail();

    expect($result['seeded'])->toContain('GF')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'overseas_region')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(1);
});
