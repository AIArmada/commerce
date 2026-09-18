<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Estonia\EstoniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 94 Estonian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'EE')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '130',
        'name' => 'Alutaguse (legacy)',
        'label' => 'Alutaguse (legacy)',
    ]);

    app(EstoniaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Alutaguse')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(94);
});

it('maps every Estonian state code to its area', function (): void {
    $mappings = app(EstoniaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(94)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['130', '141', '142', '171', '214', '184', '191', '37', '198', '39', '205', '45', '52', '255', '245', '50', '247', '251', '272', '283', '284', '291', '293', '296', '303', '305', '317', '321', '338', '353', '56', '430', '441', '60', '431', '424', '442', '432', '446', '503', '478', '480', '486', '511', '514', '528', '557', '567', '68', '624', '586', '638', '615', '618', '622', '64', '651', '653', '663', '661', '708', '668', '71', '698', '689', '712', '74', '714', '719', '726', '732', '735', '784', '792', '793', '79', '796', '803', '809', '824', '834', '928', '855', '81', '890', '897', '84', '899', '901', '903', '907', '917', '87', '919']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(EstoniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Estonian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('EE');
    $country = AddressCountry::query()->where('iso2', 'EE')->firstOrFail();

    expect($result['seeded'])->toContain('EE')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(94)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'rural_municipality')->count())->toBe(64)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'urban_municipality')->count())->toBe(15)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(94);
});
