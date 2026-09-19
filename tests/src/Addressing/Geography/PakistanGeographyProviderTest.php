<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Pakistan\PakistanAddressFormatter;
use AIArmada\Addressing\Geography\Pakistan\PakistanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines an administrative hierarchy down to districts', function (): void {
    $hierarchies = app(PakistanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('district')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('province')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('district');
});

it('imports the Pakistani tree with state links', function (): void {
    $this->seedCountry('PK');

    $result = app(SeedCountryGeographiesAction::class)->execute('PK');
    $country = AddressCountry::query()->where('iso2', 'PK')->firstOrFail();

    expect($result['seeded'])->toContain('PK')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(181)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'territory')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(174)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);

    $area = AddressArea::query()->where('source_id', 'pk:territory:azad-jammu-and-kashmir')->firstOrFail();

    expect(AddressAreaName::query()
        ->where('address_area_id', $area->getKey())
        ->where('name', 'Azad Kashmir')
        ->exists())->toBeTrue();

    $child = AddressArea::query()->where('source_id', 'pk:district:lahore')->firstOrFail();
    $parent = AddressArea::query()->where('source_id', 'pk:province:punjab')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $parent->getKey())
        ->where('child_address_area_id', $child->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue();
});

it('formats Pakistani addresses with dash-separated postcodes', function (): void {
    $formatted = app(PakistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'House No 17-B',
        'city' => 'ISLAMABAD',
        'postcode' => '44000',
        'country_code' => 'PK',
    ]));

    expect($formatted)->toBe("House No 17-B\nISLAMABAD-44000\nPakistan");
});
