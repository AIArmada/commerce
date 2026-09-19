<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Armenia\ArmeniaAddressFormatter;
use AIArmada\Addressing\Geography\Armenia\ArmeniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ArmeniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Armenian tree with state links', function (): void {
    $this->seedCountry('AM');

    $result = app(SeedCountryGeographiesAction::class)->execute('AM');
    $country = AddressCountry::query()->where('iso2', 'AM')->firstOrFail();

    expect($result['seeded'])->toContain('AM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(11);
});

it('formats Armenian addresses with the postcode left of the locality', function (): void {
    $formatted = app(ArmeniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Saryan str 22 apt 25',
        'city' => 'YEREVAN',
        'postcode' => '0002',
        'country_code' => 'AM',
    ]));

    expect($formatted)->toBe("Saryan str 22 apt 25\n0002 YEREVAN\nArmenia");
});
it('formats rural Armenian addresses with the region below the postcode line', function (): void {
    $formatted = app(ArmeniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Mashtots Street 1',
        'city' => 'Vedi',
        'state' => 'Ararat',
        'postcode' => '0601',
        'country_code' => 'AM',
    ]));

    expect($formatted)->toBe("Mashtots Street 1\n0601 Vedi\nArarat\nArmenia");
});
