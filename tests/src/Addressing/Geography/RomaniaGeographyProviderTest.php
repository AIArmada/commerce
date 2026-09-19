<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Romania\RomaniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(RomaniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Romanian tree with state links', function (): void {
    $this->seedCountry('RO');

    $result = app(SeedCountryGeographiesAction::class)->execute('RO');
    $country = AddressCountry::query()->where('iso2', 'RO')->firstOrFail();

    expect($result['seeded'])->toContain('RO')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(42)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(42)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(41)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(42);
});
