<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintKittsAndNevis\SaintKittsAndNevisAddressFormatter;
use AIArmada\Addressing\Geography\SaintKittsAndNevis\SaintKittsAndNevisGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a two-level island and parish hierarchy', function (): void {
    $hierarchies = app(SaintKittsAndNevisGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('island')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('parish')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('island')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('parish');
});

it('imports the island and parish trees with state links', function (): void {
    $this->seedCountry('KN');

    $result = app(SeedCountryGeographiesAction::class)->execute('KN');
    $country = AddressCountry::query()->where('iso2', 'KN')->firstOrFail();

    expect($result['seeded'])->toContain('KN')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'parish')->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('level', 2)->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(2);

    $basseterre = AddressArea::query()->where('source_id', 'kn:parish:saint-george-basseterre')->firstOrFail();
    $kitts = AddressArea::query()->where('source_id', 'kn:state:saint-kitts')->firstOrFail();
    $charlestown = AddressArea::query()->where('source_id', 'kn:parish:saint-paul-charlestown')->firstOrFail();
    $nevis = AddressArea::query()->where('source_id', 'kn:state:nevis')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $kitts->getKey())
        ->where('child_address_area_id', $basseterre->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue()
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $nevis->getKey())
            ->where('child_address_area_id', $charlestown->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();
});

it('formats Kittitian addresses with the postcode below the island', function (): void {
    $formatted = app(SaintKittsAndNevisAddressFormatter::class)->format(AddressData::from([
        'line1' => '2 Fern Street',
        'line2' => 'Greenlands',
        'city' => 'Basseterre',
        'state' => 'St Kitts',
        'postcode' => 'KN0101',
        'country_code' => 'KN',
    ]));

    expect($formatted)->toBe("2 Fern Street\nGreenlands\nBasseterre\nSt Kitts\nKN0101\nSaint Kitts and Nevis");
});
it('formats Nevis addresses with the island postcode', function (): void {
    $formatted = app(SaintKittsAndNevisAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Main Street',
        'city' => 'Charlestown',
        'state' => 'Nevis',
        'postcode' => 'KN0902',
        'country_code' => 'KN',
    ]));

    expect($formatted)->toBe("Main Street\nCharlestown\nNevis\nKN0902\nSaint Kitts and Nevis");
});
