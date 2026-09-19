<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\MarshallIslands\MarshallIslandsAddressFormatter;
use AIArmada\Addressing\Geography\MarshallIslands\MarshallIslandsGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MarshallIslandsGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Marshallese tree with state links', function (): void {
    $this->seedCountry('MH');

    $result = app(SeedCountryGeographiesAction::class)->execute('MH');
    $country = AddressCountry::query()->where('iso2', 'MH')->firstOrFail();

    expect($result['seeded'])->toContain('MH')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(26)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(26)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(24)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'chain')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(26);
});

it('formats Marshallese addresses with the US ZIP layout', function (): void {
    $formatted = app(MarshallIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 175',
        'city' => 'Majuro',
        'postcode' => '96960',
        'country_code' => 'MH',
    ]));

    expect($formatted)->toBe("P.O. Box 175\nMajuro MH 96960\nMarshall Islands");
});
it('formats Ebeye addresses with its own ZIP', function (): void {
    $formatted = app(MarshallIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 7',
        'city' => 'Ebeye',
        'postcode' => '96970',
        'country_code' => 'MH',
    ]));

    expect($formatted)->toBe("P.O. Box 7\nEbeye MH 96970\nMarshall Islands");
});
