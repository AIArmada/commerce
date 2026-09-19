<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Spain\SpainGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines an administrative hierarchy down to provinces', function (): void {
    $hierarchies = app(SpainGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels[0]->key)->toBe('community')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('province')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('community')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('province');
});

it('imports the Spanish tree with state links', function (): void {
    $this->seedCountry('ES');

    $result = app(SeedCountryGeographiesAction::class)->execute('ES');
    $country = AddressCountry::query()->where('iso2', 'ES')->firstOrFail();

    expect($result['seeded'])->toContain('ES')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(69)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(69)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_community')->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_city')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(50)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(19);

    $child = AddressArea::query()->where('source_id', 'es:province:toledo')->firstOrFail();
    $parent = AddressArea::query()->where('source_id', 'es:autonomous_community:castilla-la-mancha')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $parent->getKey())
        ->where('child_address_area_id', $child->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue();
});
