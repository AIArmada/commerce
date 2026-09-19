<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Niue\NiueAddressFormatter;
use AIArmada\Addressing\Geography\Niue\NiueGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NiueGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('village')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Niuean tree with state links', function (): void {
    $this->seedCountry('NU');

    $result = app(SeedCountryGeographiesAction::class)->execute('NU');
    $country = AddressCountry::query()->where('iso2', 'NU')->firstOrFail();

    expect($result['seeded'])->toContain('NU')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'village')->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});

it('formats Niue addresses with the sole code right of the locality', function (): void {
    $formatted = app(NiueAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 39',
        'city' => 'Alofi Central',
        'postcode' => '9974',
        'country_code' => 'NU',
    ]));

    expect($formatted)->toBe("PO Box 39\nAlofi Central 9974\nNiue");
});

it('uses the same island code for every village', function (): void {
    $formatted = app(NiueAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Huihui Road',
        'city' => 'Alofi',
        'postcode' => '9974',
        'country_code' => 'NU',
    ]));

    expect($formatted)->toBe("Huihui Road\nAlofi 9974\nNiue");
});
