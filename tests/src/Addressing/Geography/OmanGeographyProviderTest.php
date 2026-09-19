<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Oman\OmanAddressFormatter;
use AIArmada\Addressing\Geography\Oman\OmanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines an administrative hierarchy down to wilayats', function (): void {
    $hierarchies = app(OmanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('wilayat')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('governorate')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('wilayat');
});

it('imports the Omani tree with state links', function (): void {
    $this->seedCountry('OM');

    $result = app(SeedCountryGeographiesAction::class)->execute('OM');
    $country = AddressCountry::query()->where('iso2', 'OM')->firstOrFail();

    expect($result['seeded'])->toContain('OM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(74)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'wilayat')->count())->toBe(63)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(11);

    $child = AddressArea::query()->where('source_id', 'om:wilayat:sohar')->firstOrFail();
    $parent = AddressArea::query()->where('source_id', 'om:governorate:al-batinah-north')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $parent->getKey())
        ->where('child_address_area_id', $child->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue();
});

it('formats Omani addresses with the postcode above the locality', function (): void {
    $formatted = app(OmanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 15',
        'city' => 'AL-KHOER',
        'postcode' => '133',
        'country_code' => 'OM',
    ]));

    expect($formatted)->toBe("P.O. Box 15\n133\nAL-KHOER\nOman");
});
