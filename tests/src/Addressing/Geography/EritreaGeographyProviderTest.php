<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Eritrea\EritreaAddressFormatter;
use AIArmada\Addressing\Geography\Eritrea\EritreaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(EritreaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Eritrean tree with state links', function (): void {
    $this->seedCountry('ER');

    $result = app(SeedCountryGeographiesAction::class)->execute('ER');
    $country = AddressCountry::query()->where('iso2', 'ER')->firstOrFail();

    expect($result['seeded'])->toContain('ER')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});

it('formats Eritrean addresses without a postcode system', function (): void {
    $formatted = app(EritreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Awet Street 4',
        'city' => 'ASMARA',
        'country_code' => 'ER',
    ]));

    expect($formatted)->toBe("Awet Street 4\nASMARA\nEritrea");
});
it('prints any supplied Eritrean code on its own line', function (): void {
    $formatted = app(EritreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Awet Street 4',
        'city' => 'ASMARA',
        'postcode' => '99999',
        'country_code' => 'ER',
    ]));

    expect($formatted)->toBe("Awet Street 4\nASMARA\n99999\nEritrea");
});
