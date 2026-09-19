<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\India\IndiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IndiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Indian tree with state links', function (): void {
    $this->seedCountry('IN');

    $result = app(SeedCountryGeographiesAction::class)->execute('IN');
    $country = AddressCountry::query()->where('iso2', 'IN')->firstOrFail();

    expect($result['seeded'])->toContain('IN')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(36)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(36)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(28)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'union_territory')->count())->toBe(8)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(36);
});
