<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Indonesia\IndonesiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('removes island-group stragglers seeded before the bundled data fix', function (): void {
    $country = $this->seedCountry('ID');
    State::query()->create([
        'country_id' => $country->id,
        'code' => 'PP',
        'name' => 'Papua',
        'label' => 'Papua',
    ]);

    app(IndonesiaGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->where('code', 'PP')->exists())->toBeFalse()
        ->and(State::query()->where('country_id', $country->id)->where('name', 'Papua')->value('code'))->toBe('PA');
});

it('defines a single administrative hierarchy down to districts', function (): void {
    $hierarchies = app(IndonesiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('regency')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('province')
        ->and($hierarchies[0]->levels[2]->key)->toBe('district')
        ->and($hierarchies[0]->levels[2]->parentKey)->toBe('regency');
});

it('imports the province, regency, and district trees with state links', function (): void {
    $this->seedCountry('ID');

    $result = app(SeedCountryGeographiesAction::class)->execute('ID');
    $country = AddressCountry::query()->where('iso2', 'ID')->firstOrFail();

    expect($result['seeded'])->toContain('ID')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(38)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'PE')->value('name'))->toBe('Papua Pegunungan')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7837)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(38)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'regency')->count())->toBe(416)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(98)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(7285)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(38);

    $bandung = AddressArea::query()->where('source_id', 'id:regency:3273')->firstOrFail();
    $jabar = AddressArea::query()->where('source_id', 'id:province:32')->firstOrFail();
    $coblong = AddressArea::query()->where('source_id', 'id:district:327302')->firstOrFail();

    expect($bandung->name)->toBe('Kota Bandung')
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $jabar->getKey())
            ->where('child_address_area_id', $bandung->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue()
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $bandung->getKey())
            ->where('child_address_area_id', $coblong->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();

    $papua = AddressArea::query()->where('source_id', 'id:province:91')->firstOrFail();

    expect(AddressAreaName::query()
        ->where('address_area_id', $papua->getKey())
        ->where('name', 'Irian Jaya')
        ->exists())->toBeTrue();
});
