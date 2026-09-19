<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Malta\MaltaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MaltaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('local_council')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Maltese tree with state links', function (): void {
    $this->seedCountry('MT');

    $result = app(SeedCountryGeographiesAction::class)->execute('MT');
    $country = AddressCountry::query()->where('iso2', 'MT')->firstOrFail();

    expect($result['seeded'])->toContain('MT')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(68)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(68)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'local_council')->count())->toBe(68)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(68);
});
