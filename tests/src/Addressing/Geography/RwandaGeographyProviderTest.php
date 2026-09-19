<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Rwanda\RwandaAddressFormatter;
use AIArmada\Addressing\Geography\Rwanda\RwandaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(RwandaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Rwandan tree with state links', function (): void {
    $this->seedCountry('RW');

    $result = app(SeedCountryGeographiesAction::class)->execute('RW');
    $country = AddressCountry::query()->where('iso2', 'RW')->firstOrFail();

    expect($result['seeded'])->toContain('RW')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});

it('formats Rwandan addresses without a postcode system', function (): void {
    $formatted = app(RwandaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 3425',
        'city' => 'KIGALI',
        'country_code' => 'RW',
    ]));

    expect($formatted)->toBe("B.P. 3425\nKIGALI\nRwanda");
});
it('formats Rwandan addresses with the province below the locality', function (): void {
    $formatted = app(RwandaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'KN 3 Road',
        'city' => 'Butare',
        'state' => 'Southern',
        'country_code' => 'RW',
    ]));

    expect($formatted)->toBe("KN 3 Road\nButare\nSouthern\nRwanda");
});
