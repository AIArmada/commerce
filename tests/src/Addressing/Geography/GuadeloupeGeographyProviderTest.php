<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Guadeloupe\GuadeloupeGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GuadeloupeGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Guadeloupean tree with state links', function (): void {
    $this->seedCountry('GP');

    $result = app(SeedCountryGeographiesAction::class)->execute('GP');
    $country = AddressCountry::query()->where('iso2', 'GP')->firstOrFail();

    expect($result['seeded'])->toContain('GP')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(2);
});
