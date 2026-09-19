<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Malawi\MalawiAddressFormatter;
use AIArmada\Addressing\Geography\Malawi\MalawiGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a two-level region and district hierarchy', function (): void {
    $hierarchies = app(MalawiGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('district')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('region');
});

it('imports the region and district trees with state links', function (): void {
    $this->seedCountry('MW');

    $result = app(SeedCountryGeographiesAction::class)->execute('MW');
    $country = AddressCountry::query()->where('iso2', 'MW')->firstOrFail();

    expect($result['seeded'])->toContain('MW')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(31)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(28)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('level', 2)->count())->toBe(28)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(3);

    $lilongwe = AddressArea::query()->where('source_id', 'mw:district:lilongwe')->firstOrFail();
    $central = AddressArea::query()->where('source_id', 'mw:region:central')->firstOrFail();
    $mzimba = AddressArea::query()->where('source_id', 'mw:district:mzimba')->firstOrFail();
    $northern = AddressArea::query()->where('source_id', 'mw:region:northern')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $central->getKey())
        ->where('child_address_area_id', $lilongwe->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue()
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $northern->getKey())
            ->where('child_address_area_id', $mzimba->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();
});

it('formats Malawian addresses with the postcode left of the locality', function (): void {
    $formatted = app(MalawiAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Dunduzu Avenue',
        'city' => 'KASUNGU',
        'postcode' => '102010',
        'country_code' => 'MW',
    ]));

    expect($formatted)->toBe("21 Dunduzu Avenue\n102010 KASUNGU\nMalawi");
});
it('formats Malawian addresses with the region below the postcode line', function (): void {
    $formatted = app(MalawiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Chipembere Highway',
        'city' => 'Blantyre',
        'state' => 'Southern',
        'postcode' => '309070',
        'country_code' => 'MW',
    ]));

    expect($formatted)->toBe("Chipembere Highway\n309070 Blantyre\nSouthern\nMalawi");
});
