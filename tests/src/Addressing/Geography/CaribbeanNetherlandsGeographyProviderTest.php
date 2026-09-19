<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CaribbeanNetherlands\CaribbeanNetherlandsAddressFormatter;
use AIArmada\Addressing\Geography\CaribbeanNetherlands\CaribbeanNetherlandsGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CaribbeanNetherlandsGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('special_municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Caribbean Netherlands tree with state links', function (): void {
    $this->seedCountry('BQ');

    $result = app(SeedCountryGeographiesAction::class)->execute('BQ');
    $country = AddressCountry::query()->where('iso2', 'BQ')->firstOrFail();

    expect($result['seeded'])->toContain('BQ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_municipality')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(3);
});

it('formats Caribbean Netherlands addresses without a postcode system', function (): void {
    $formatted = app(CaribbeanNetherlandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Kaya Grandi 5',
        'city' => 'KRALENDIJK',
        'state' => 'Bonaire',
        'country_code' => 'BQ',
    ]));

    expect($formatted)->toBe("Kaya Grandi 5\nKRALENDIJK\nBonaire\nBonaire, Sint Eustatius and Saba");
});
it('prints any supplied Caribbean Netherlands code on its own line', function (): void {
    $formatted = app(CaribbeanNetherlandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Kaya Grandi 5',
        'city' => 'KRALENDIJK',
        'postcode' => '0000 AA',
        'country_code' => 'BQ',
    ]));

    expect($formatted)->toBe("Kaya Grandi 5\nKRALENDIJK\n0000 AA\nBonaire, Sint Eustatius and Saba");
});
