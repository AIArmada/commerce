<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Chile\ChileAddressFormatter;
use AIArmada\Addressing\Geography\Chile\ChileGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ChileGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Chilean tree with state links', function (): void {
    $this->seedCountry('CL');

    $result = app(SeedCountryGeographiesAction::class)->execute('CL');
    $country = AddressCountry::query()->where('iso2', 'CL')->firstOrFail();

    expect($result['seeded'])->toContain('CL')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(16)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(16);
});

it('formats Chilean addresses with the postcode left of the commune', function (): void {
    $formatted = app(ChileAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Moneda 1152',
        'city' => 'SANTIAGO',
        'state' => 'REGION METROPOLITANA',
        'postcode' => '8340648',
        'country_code' => 'CL',
    ]));

    expect($formatted)->toBe("Moneda 1152\n8340648 SANTIAGO\nREGION METROPOLITANA\nChile");
});
it('formats Chilean Quilicura addresses with the block postcode', function (): void {
    $formatted = app(ChileAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. Ossa 10',
        'city' => 'QUILICURA',
        'postcode' => '8720019',
        'country_code' => 'CL',
    ]));

    expect($formatted)->toBe("Av. Ossa 10\n8720019 QUILICURA\nChile");
});
