<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Bulgaria\BulgariaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 28 Bulgarian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BG')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Blagoevgrad (legacy)',
        'label' => 'Blagoevgrad (legacy)',
    ]);

    app(BulgariaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Blagoevgrad')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(28);
});

it('maps every Bulgarian state code to its area', function (): void {
    $mappings = app(BulgariaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(28)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '08', '07', '26', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '27', '19', '20', '21', '23', '22', '24', '25', '03', '04', '05', '06', '28']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BulgariaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Bulgarian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('BG');
    $country = AddressCountry::query()->where('iso2', 'BG')->firstOrFail();

    expect($result['seeded'])->toContain('BG')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(28)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(28)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(28);
});
