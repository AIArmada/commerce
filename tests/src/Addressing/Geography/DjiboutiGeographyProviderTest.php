<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiAddressFormatter;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(DjiboutiGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Djiboutian tree with state links', function (): void {
    $this->seedCountry('DJ');

    $result = app(SeedCountryGeographiesAction::class)->execute('DJ');
    $country = AddressCountry::query()->where('iso2', 'DJ')->firstOrFail();

    expect($result['seeded'])->toContain('DJ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});

it('formats Djiboutian addresses with the postcode left of the locality', function (): void {
    $formatted = app(DjiboutiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1663',
        'city' => 'DJIBOUTI VILLE',
        'postcode' => '77101',
        'country_code' => 'DJ',
    ]));

    expect($formatted)->toBe("BP 1663\n77101 DJIBOUTI VILLE\nDjibouti");
});
it('prints matching Djiboutian city and region once', function (): void {
    $formatted = app(DjiboutiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 12',
        'city' => 'Arta',
        'state' => 'Arta',
        'postcode' => '77201',
        'country_code' => 'DJ',
    ]));

    expect($formatted)->toBe("BP 12\n77201 Arta\nDjibouti");
});
