<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Morocco\MoroccoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines an administrative hierarchy down to provinces', function (): void {
    $hierarchies = app(MoroccoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('province')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('region')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('province');
});

it('imports the Moroccan tree with state links', function (): void {
    $this->seedCountry('MA');

    $result = app(SeedCountryGeographiesAction::class)->execute('MA');
    $country = AddressCountry::query()->where('iso2', 'MA')->firstOrFail();

    expect($result['seeded'])->toContain('MA')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(87)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(87)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(62)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'prefecture')->count())->toBe(13)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);

    $child = AddressArea::query()->where('source_id', 'ma:prefecture:casablanca')->firstOrFail();
    $parent = AddressArea::query()->where('source_id', 'ma:region:casablanca-settat')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $parent->getKey())
        ->where('child_address_area_id', $child->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue();
});
