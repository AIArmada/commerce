<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Malta\MaltaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 68 Maltese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MT')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Attard (legacy)',
        'label' => 'Attard (legacy)',
    ]);

    app(MaltaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Attard')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(68);
});

it('maps every Maltese state code to its area', function (): void {
    $mappings = app(MaltaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(68)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '13', '14', '15', '16', '17', '11', '12', '18', '19', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '46', '47', '49', '50', '52', '53', '54', '20', '55', '56', '48', '51', '57', '58', '59', '60', '45', '61', '62', '63', '64', '65', '66', '67', '68']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MaltaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('local_council')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Maltese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MT');
    $country = AddressCountry::query()->where('iso2', 'MT')->firstOrFail();

    expect($result['seeded'])->toContain('MT')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(68)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'local_council')->count())->toBe(68)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(68);
});
