<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Uruguay\UruguayAddressFormatter;
use AIArmada\Addressing\Geography\Uruguay\UruguayGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(UruguayGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Uruguayan tree with state links', function (): void {
    $this->seedCountry('UY');

    $result = app(SeedCountryGeographiesAction::class)->execute('UY');
    $country = AddressCountry::query()->where('iso2', 'UY')->firstOrFail();

    expect($result['seeded'])->toContain('UY')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(19)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(19)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(19)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(19);
});

it('formats Uruguayan addresses with the postcode and dash left of the locality', function (): void {
    $formatted = app(UruguayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Chaná 1215, apto. 152',
        'city' => 'ROSARIO',
        'state' => 'COLONIA',
        'postcode' => '70200',
        'country_code' => 'UY',
    ]));

    expect($formatted)->toBe("Chaná 1215, apto. 152\n70200 – ROSARIO\nCOLONIA\nUruguay");
});
it('formats Uruguayan capital addresses with the Montevideo postcode', function (): void {
    $formatted = app(UruguayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. 18 de Julio 1000',
        'city' => 'MONTEVIDEO',
        'postcode' => '11600',
        'country_code' => 'UY',
    ]));

    expect($formatted)->toBe("Av. 18 de Julio 1000\n11600 – MONTEVIDEO\nUruguay");
});
