<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Nigeria\NigeriaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NigeriaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Nigerian tree with state links', function (): void {
    $this->seedCountry('NG');

    $result = app(SeedCountryGeographiesAction::class)->execute('NG');
    $country = AddressCountry::query()->where('iso2', 'NG')->firstOrFail();

    expect($result['seeded'])->toContain('NG')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(37)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(37)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(37)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(37);
});
