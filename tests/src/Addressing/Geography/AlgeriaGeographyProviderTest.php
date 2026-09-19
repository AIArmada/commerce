<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Algeria\AlgeriaAddressFormatter;
use AIArmada\Addressing\Geography\Algeria\AlgeriaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AlgeriaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('wilaya')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Algerian tree with state links', function (): void {
    $this->seedCountry('DZ');

    $result = app(SeedCountryGeographiesAction::class)->execute('DZ');
    $country = AddressCountry::query()->where('iso2', 'DZ')->firstOrFail();

    expect($result['seeded'])->toContain('DZ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(69)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(69)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'wilaya')->count())->toBe(69)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(69)
        ->and(State::query()->where('country_id', $country->id)->where('code', '69')->exists())->toBeTrue()
        ->and(State::query()->where('country_id', $country->id)->where('code', '49')->value('name'))->toBe('Timimoun');
});

it('formats Algerian addresses with the postcode left of the locality', function (): void {
    $formatted = app(AlgeriaAddressFormatter::class)->format(AddressData::from([
        'line1' => "2, rue de l'Indépendance",
        'city' => 'ALGIERS',
        'postcode' => '16027',
        'country_code' => 'DZ',
    ]));

    expect($formatted)->toBe("2, rue de l'Indépendance\n16027 ALGIERS\nAlgeria");
});
