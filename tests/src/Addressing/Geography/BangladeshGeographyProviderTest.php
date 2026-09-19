<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bangladesh\BangladeshAddressFormatter;
use AIArmada\Addressing\Geography\Bangladesh\BangladeshGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

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
    $this->seedCountry('BD');

    $result = app(SeedCountryGeographiesAction::class)->execute('BD');
    $country = AddressCountry::query()->where('iso2', 'BD')->firstOrFail();

    expect($result['seeded'])->toContain('BD')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(72)
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

it('formats Bangladeshi addresses with spaced dash postcodes and thana', function (): void {
    $formatted = app(BangladeshAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Vil Genda',
        'components' => ['thana' => 'Savar'],
        'city' => 'DHAKA',
        'postcode' => '1340',
        'country_code' => 'BD',
    ]));

    expect($formatted)->toBe("Vil Genda\nSavar\nDHAKA - 1340\nBangladesh");
});
