<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bahamas\BahamasAddressFormatter;
use AIArmada\Addressing\Geography\Bahamas\BahamasGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BahamasGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Bahamian tree with state links', function (): void {
    $this->seedCountry('BS');

    $result = app(SeedCountryGeographiesAction::class)->execute('BS');
    $country = AddressCountry::query()->where('iso2', 'BS')->firstOrFail();

    expect($result['seeded'])->toContain('BS')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(32)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(32)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(31)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'island')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(32);
});

it('formats Bahamian addresses without a postcode system', function (): void {
    $formatted = app(BahamasAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box GT 2001',
        'city' => 'Nassau',
        'country_code' => 'BS',
    ]));

    expect($formatted)->toBe("P.O. Box GT 2001\nNassau\nThe Bahamas");
});
it('prints any supplied Bahamian code on its own line', function (): void {
    $formatted = app(BahamasAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box N-8302',
        'city' => 'Nassau',
        'postcode' => '99999',
        'country_code' => 'BS',
    ]));

    expect($formatted)->toBe("P.O. Box N-8302\nNassau\n99999\nThe Bahamas");
});
