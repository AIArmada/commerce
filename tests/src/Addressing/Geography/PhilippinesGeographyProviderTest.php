<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Philippines\PhilippinesGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 99 Filipino states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'PH')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'WSA',
        'name' => 'Western Samar',
        'label' => 'Western Samar',
    ]);

    app(PhilippinesGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Samar')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(99);
});

it('maps every Filipino state code to its area', function (): void {
    $mappings = app(PhilippinesGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(82)
        ->and(array_keys($mappings))->toBe(['ABR', 'AGN', 'AGS', 'AKL', 'ALB', 'ANT', 'APA', 'AUR', 'BAN', 'BAS', 'BEN', 'BIL', 'BOH', 'BTG', 'BTN', 'BUK', 'BUL', 'CAG', 'CAM', 'CAN', 'CAP', 'CAS', 'CAT', 'CAV', 'CEB', 'COM', 'DAO', 'DAS', 'DAV', 'DIN', 'DVO', 'EAS', 'GUI', 'IFU', 'ILI', 'ILN', 'ILS', 'ISA', 'KAL', 'LAG', 'LAN', 'LAS', 'LEY', 'LUN', 'MAD', 'MAS', 'MDC', 'MDR', 'MGN', 'MGS', 'MOU', 'MSC', 'MSR', 'NCO', 'NEC', 'NER', 'NSA', 'NUE', 'NUV', 'PAM', 'PAN', 'PLW', 'QUE', 'QUI', 'RIZ', 'ROM', 'SAR', 'SCO', 'SIG', 'SLE', 'SLU', 'SOR', 'SUK', 'SUN', 'SUR', 'TAR', 'TAW', 'WSA', 'ZAN', 'ZAS', 'ZMB', 'ZSI']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PhilippinesGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Filipino tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('PH');
    $country = AddressCountry::query()->where('iso2', 'PH')->firstOrFail();

    expect($result['seeded'])->toContain('PH')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(82)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(82)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(82);

    $area = AddressArea::query()->where('source_id', 'ph:province:samar')->firstOrFail();

    expect(AddressAreaName::query()
        ->where('address_area_id', $area->getKey())
        ->where('name', 'Western Samar')
        ->exists())->toBeTrue();
});
