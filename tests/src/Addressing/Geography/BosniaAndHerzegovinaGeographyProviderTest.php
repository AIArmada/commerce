<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaAddressFormatter;
use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BosniaAndHerzegovinaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('entity')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Bosnian tree with state links', function (): void {
    $this->seedCountry('BA');

    $result = app(SeedCountryGeographiesAction::class)->execute('BA');
    $country = AddressCountry::query()->where('iso2', 'BA')->firstOrFail();

    expect($result['seeded'])->toContain('BA')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'entity')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(3);
});

it('formats Bosnian addresses with the postcode left of the locality', function (): void {
    $formatted = app(BosniaAndHerzegovinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Semira Fraste E6/6',
        'city' => 'SARAJEVO',
        'postcode' => '71000',
        'country_code' => 'BA',
    ]));

    expect($formatted)->toBe("Semira Fraste E6/6\n71000 SARAJEVO\nBosnia and Herzegovina");
});
it('formats Bosnian rural addresses with the numberless street line', function (): void {
    $formatted = app(BosniaAndHerzegovinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Sapna BB',
        'city' => 'SAPNA',
        'postcode' => '75411',
        'country_code' => 'BA',
    ]));

    expect($formatted)->toBe("Sapna BB\n75411 SAPNA\nBosnia and Herzegovina");
});
