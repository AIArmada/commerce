<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Bangladesh\BangladeshGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 72 Bangladeshi states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BD')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'A',
        'name' => 'Barisal',
        'label' => 'Barisal',
    ]);

    app(BangladeshGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Barishal')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(72);
});

it('maps every Bangladeshi state code to its area', function (): void {
    $mappings = app(BangladeshGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(8)
        ->and(array_keys($mappings))->toBe(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);
});

it('defines an administrative hierarchy down to districts', function (): void {
    $hierarchies = app(BangladeshGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels[0]->key)->toBe('division')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('district')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('division')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('district');
});

it('imports the Bangladeshi tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('BD');
    $country = AddressCountry::query()->where('iso2', 'BD')->firstOrFail();

    expect($result['seeded'])->toContain('BD')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(72)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'division')->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(64)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(8);

    $child = AddressArea::query()->where('source_id', 'bd:district:cox-s-bazar')->firstOrFail();
    $parent = AddressArea::query()->where('source_id', 'bd:division:chattogram')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $parent->getKey())
        ->where('child_address_area_id', $child->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue();
});
