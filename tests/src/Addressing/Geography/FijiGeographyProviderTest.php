<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Fiji\FijiAddressFormatter;
use AIArmada\Addressing\Geography\Fiji\FijiGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a two-level division and province hierarchy', function (): void {
    $hierarchies = app(FijiGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('division')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('province')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('division');
});

it('imports the division and province trees with state links', function (): void {
    $this->seedCountry('FJ');

    $result = app(SeedCountryGeographiesAction::class)->execute('FJ');
    $country = AddressCountry::query()->where('iso2', 'FJ')->firstOrFail();

    expect($result['seeded'])->toContain('FJ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(19)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'division')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'dependency')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('level', 2)->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);

    $ba = AddressArea::query()->where('source_id', 'fj:province:ba')->firstOrFail();
    $western = AddressArea::query()->where('source_id', 'fj:division:western')->firstOrFail();
    $cakaudrove = AddressArea::query()->where('source_id', 'fj:province:cakaudrove')->firstOrFail();
    $northern = AddressArea::query()->where('source_id', 'fj:division:northern')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $western->getKey())
        ->where('child_address_area_id', $ba->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue()
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $northern->getKey())
            ->where('child_address_area_id', $cakaudrove->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();
});

it('formats Fijian addresses without a postcode system', function (): void {
    $formatted = app(FijiAddressFormatter::class)->format(AddressData::from([
        'line1' => '14 VIRIA STREET',
        'line2' => 'VATUWAQA',
        'city' => 'SUVA',
        'country_code' => 'FJ',
    ]));

    expect($formatted)->toBe("14 VIRIA STREET\nVATUWAQA\nSUVA\nFiji Islands");
});
it('prints any supplied Fijian code on its own line', function (): void {
    $formatted = app(FijiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 123',
        'city' => 'SUVA',
        'postcode' => '9999',
        'country_code' => 'FJ',
    ]));

    expect($formatted)->toBe("PO Box 123\nSUVA\n9999\nFiji Islands");
});
