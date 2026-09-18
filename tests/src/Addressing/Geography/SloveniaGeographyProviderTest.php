<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Slovenia\SloveniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 212 Slovenian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SI')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '001',
        'name' => 'Ajdovščina (legacy)',
        'label' => 'Ajdovščina (legacy)',
    ]);

    app(SloveniaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Ajdovščina')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(212);
});

it('maps every Slovenian state code to its area', function (): void {
    $mappings = app(SloveniaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(212)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['001', '213', '195', '002', '148', '149', '003', '150', '004', '005', '006', '151', '007', '009', '008', '152', '011', '012', '013', '014', '153', '196', '015', '016', '017', '018', '019', '154', '020', '155', '021', '156', '022', '157', '023', '024', '025', '026', '027', '028', '207', '029', '030', '031', '158', '032', '159', '160', '161', '162', '034', '035', '036', '037', '038', '039', '040', '041', '163', '042', '043', '044', '045', '046', '047', '048', '049', '164', '050', '197', '165', '051', '052', '053', '166', '054', '055', '056', '057', '058', '059', '060', '061', '062', '063', '208', '064', '065', '066', '167', '067', '068', '069', '198', '070', '168', '071', '072', '073', '074', '169', '075', '212', '170', '076', '199', '077', '078', '079', '080', '081', '082', '083', '084', '085', '086', '171', '087', '088', '089', '090', '091', '092', '172', '093', '200', '173', '094', '174', '095', '175', '096', '097', '098', '099', '100', '101', '102', '103', '176', '209', '201', '104', '177', '106', '105', '107', '108', '033', '178', '109', '183', '117', '118', '119', '120', '211', '110', '111', '121', '122', '123', '112', '113', '114', '124', '206', '125', '194', '179', '180', '126', '202', '115', '127', '203', '181', '204', '182', '116', '210', '205', '184', '010', '128', '129', '130', '185', '131', '186', '132', '133', '187', '134', '188', '135', '136', '137', '138', '139', '189', '140', '141', '142', '190', '143', '146', '191', '147', '192', '144', '193']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SloveniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Slovenian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SI');
    $country = AddressCountry::query()->where('iso2', 'SI')->firstOrFail();

    expect($result['seeded'])->toContain('SI')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(212)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(200)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'urban_municipality')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(212);
});
