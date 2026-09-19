<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Paraguay\ParaguayAddressFormatter;
use AIArmada\Addressing\Geography\Paraguay\ParaguayGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ParaguayGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Paraguayan tree with state links', function (): void {
    $this->seedCountry('PY');

    $result = app(SeedCountryGeographiesAction::class)->execute('PY');
    $country = AddressCountry::query()->where('iso2', 'PY')->firstOrFail();

    expect($result['seeded'])->toContain('PY')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(18)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(18);
});

it('formats Paraguayan addresses with the postcode left of the locality', function (): void {
    $formatted = app(ParaguayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Estrella Nº 340, casi Yegros',
        'line2' => 'Edif. España, Bloque A, Piso 3, Depto. 10',
        'city' => 'ASUNCIÓN',
        'state' => 'CENTRAL',
        'postcode' => '001218',
        'country_code' => 'PY',
    ]));

    expect($formatted)->toBe("Estrella Nº 340, casi Yegros\nEdif. España, Bloque A, Piso 3, Depto. 10\n001218 ASUNCIÓN\nCENTRAL\nParaguay");
});
it('formats Paraguayan rural addresses with the town postcode', function (): void {
    $formatted = app(ParaguayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ruta 1 km 45',
        'city' => 'ALBERDI',
        'state' => 'ÑEEMBUCU',
        'postcode' => '120203',
        'country_code' => 'PY',
    ]));

    expect($formatted)->toBe("Ruta 1 km 45\n120203 ALBERDI\nÑEEMBUCU\nParaguay");
});
