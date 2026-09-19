<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Thailand\ThailandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ThailandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Thai tree with state links', function (): void {
    $this->seedCountry('TH');

    $result = app(SeedCountryGeographiesAction::class)->execute('TH');
    $country = AddressCountry::query()->where('iso2', 'TH')->firstOrFail();

    expect($result['seeded'])->toContain('TH')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(78)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(78)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'metropolitan_administration')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(76)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(78);
});
