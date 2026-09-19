<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TurkiyeGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Turkish tree with state links', function (): void {
    $this->seedCountry('TR');

    $result = app(SeedCountryGeographiesAction::class)->execute('TR');
    $country = AddressCountry::query()->where('iso2', 'TR')->firstOrFail();

    expect($result['seeded'])->toContain('TR')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(81)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(81)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(81)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(81);
});
