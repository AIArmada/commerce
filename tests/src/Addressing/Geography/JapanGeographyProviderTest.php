<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Japan\JapanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(JapanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('prefecture')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Japanese tree with state links', function (): void {
    $this->seedCountry('JP');

    $result = app(SeedCountryGeographiesAction::class)->execute('JP');
    $country = AddressCountry::query()->where('iso2', 'JP')->firstOrFail();

    expect($result['seeded'])->toContain('JP')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(47)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(47)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'prefecture')->count())->toBe(47)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(47);
});
