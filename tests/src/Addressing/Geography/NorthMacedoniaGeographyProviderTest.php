<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\NorthMacedonia\NorthMacedoniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 80 Macedonian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MK')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '801',
        'name' => 'Aerodrom (legacy)',
        'label' => 'Aerodrom (legacy)',
    ]);

    app(NorthMacedoniaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Aerodrom')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(80);
});

it('maps every Macedonian state code to its area', function (): void {
    $mappings = app(NorthMacedoniaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(80)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['801', '802', '201', '501', '401', '601', '402', '602', '803', '815', '109', '814', '313', '210', '816', '303', '304', '203', '502', '103', '406', '503', '804', '405', '805', '604', '102', '807', '606', '205', '808', '104', '307', '809', '206', '407', '701', '702', '504', '505', '703', '704', '105', '207', '308', '607', '506', '106', '507', '408', '310', '208', '810', '311', '508', '209', '409', '705', '509', '107', '811', '812', '706', '211', '312', '410', '813', '817', '108', '608', '609', '403', '404', '101', '301', '202', '603', '806', '605', '204']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NorthMacedoniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Macedonian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MK');
    $country = AddressCountry::query()->where('iso2', 'MK')->firstOrFail();

    expect($result['seeded'])->toContain('MK')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(80)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(80)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(80);
});
