<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kuwait\KuwaitAddressFormatter;
use AIArmada\Addressing\Geography\Kuwait\KuwaitGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a two-level governorate and area hierarchy', function (): void {
    $hierarchies = app(KuwaitGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('area')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('governorate')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('area');
});

it('imports the governorate and area trees with state links', function (): void {
    $this->seedCountry('KW');

    $result = app(SeedCountryGeographiesAction::class)->execute('KW');
    $country = AddressCountry::query()->where('iso2', 'KW')->firstOrFail();

    expect($result['seeded'])->toContain('KW')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(140)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'area')->count())->toBe(134)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('level', 2)->count())->toBe(134)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);

    $salmiya = AddressArea::query()->where('source_id', 'kw:area:salmiya')->firstOrFail();
    $hawalli = AddressArea::query()->where('source_id', 'kw:governorate:hawalli')->firstOrFail();
    $fahaheel = AddressArea::query()->where('source_id', 'kw:area:fahaheel')->firstOrFail();
    $ahmadi = AddressArea::query()->where('source_id', 'kw:governorate:al-ahmadi')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $hawalli->getKey())
        ->where('child_address_area_id', $salmiya->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue()
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $ahmadi->getKey())
            ->where('child_address_area_id', $fahaheel->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();
});

it('formats Kuwaiti addresses with the postcode left of the locality', function (): void {
    $formatted = app(KuwaitAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al-Sabbahiya',
        'city' => 'KUWAIT',
        'postcode' => '54551',
        'country_code' => 'KW',
    ]));

    expect($formatted)->toBe("Al-Sabbahiya\n54551 KUWAIT\nKuwait");
});
