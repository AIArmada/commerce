<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauAddressFormatter;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GuineaBissauGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Bissau-Guinean tree with state links', function (): void {
    $this->seedCountry('GW');

    $result = app(SeedCountryGeographiesAction::class)->execute('GW');
    $country = AddressCountry::query()->where('iso2', 'GW')->firstOrFail();

    expect($result['seeded'])->toContain('GW')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_sector')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});

it('formats Bissau-Guinean addresses with the postcode left of the locality', function (): void {
    $formatted = app(GuineaBissauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua Justino Lopes 12C',
        'city' => 'BISSAU',
        'postcode' => '1000',
        'country_code' => 'GW',
    ]));

    expect($formatted)->toBe("Rua Justino Lopes 12C\n1000 BISSAU\nGuinea-Bissau");
});
it('formats Bissau-Guinean addresses without a postcode when missing', function (): void {
    $formatted = app(GuineaBissauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua Justino Lopes 12C',
        'city' => 'BISSAU',
        'country_code' => 'GW',
    ]));

    expect($formatted)->toBe("Rua Justino Lopes 12C\nBISSAU\nGuinea-Bissau");
});
