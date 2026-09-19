<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SriLanka\SriLankaAddressFormatter;
use AIArmada\Addressing\Geography\SriLanka\SriLankaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a two-level province and district hierarchy', function (): void {
    $hierarchies = app(SriLankaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('district')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('province');
});

it('imports the province and district trees with state links', function (): void {
    $this->seedCountry('LK');

    $result = app(SeedCountryGeographiesAction::class)->execute('LK');
    $country = AddressCountry::query()->where('iso2', 'LK')->firstOrFail();

    expect($result['seeded'])->toContain('LK')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(34)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('level', 2)->count())->toBe(25)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);

    $kandy = AddressArea::query()->where('source_id', 'lk:district:kandy')->firstOrFail();
    $central = AddressArea::query()->where('source_id', 'lk:province:central')->firstOrFail();
    $jaffna = AddressArea::query()->where('source_id', 'lk:district:jaffna')->firstOrFail();
    $northern = AddressArea::query()->where('source_id', 'lk:province:northern')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $central->getKey())
        ->where('child_address_area_id', $kandy->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue()
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $northern->getKey())
            ->where('child_address_area_id', $jaffna->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();
});

it('formats Sri Lankan addresses with the postcode below the locality', function (): void {
    $formatted = app(SriLankaAddressFormatter::class)->format(AddressData::from([
        'line1' => '201 Shanti Villa',
        'line2' => 'Silkhouse Street',
        'city' => 'KANDY',
        'postcode' => '20000',
        'country_code' => 'LK',
    ]));

    expect($formatted)->toBe("201 Shanti Villa\nSilkhouse Street\nKANDY\n20000\nSri Lanka");
});
it('formats Sri Lankan addresses keeping the province above the postcode line', function (): void {
    $formatted = app(SriLankaAddressFormatter::class)->format(AddressData::from([
        'line1' => '201 Shanti Villa',
        'city' => 'KANDY',
        'state' => 'Central',
        'postcode' => '20000',
        'country_code' => 'LK',
    ]));

    expect($formatted)->toBe("201 Shanti Villa\nKANDY\nCentral\n20000\nSri Lanka");
});
