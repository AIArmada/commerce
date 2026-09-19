<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\NewCaledonia\NewCaledoniaAddressFormatter;
use AIArmada\Addressing\Geography\NewCaledonia\NewCaledoniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NewCaledoniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the New Caledonian tree with state links', function (): void {
    $this->seedCountry('NC');

    $result = app(SeedCountryGeographiesAction::class)->execute('NC');
    $country = AddressCountry::query()->where('iso2', 'NC')->firstOrFail();

    expect($result['seeded'])->toContain('NC')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(3);
});

it('formats New Caledonian addresses with the code left of the locality', function (): void {
    $formatted = app(NewCaledoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => '24 RUE DES PALMIERS',
        'city' => 'NOUMEA',
        'postcode' => '98800',
        'country_code' => 'NC',
    ]));

    expect($formatted)->toBe("24 RUE DES PALMIERS\n98800 NOUMEA\nNew Caledonia");
});
it('formats Mont-Dore addresses with their own code', function (): void {
    $formatted = app(NewCaledoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 485',
        'city' => 'MONT-DORE',
        'postcode' => '98810',
        'country_code' => 'NC',
    ]));

    expect($formatted)->toBe("BP 485\n98810 MONT-DORE\nNew Caledonia");
});
