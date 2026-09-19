<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Montenegro\MontenegroAddressFormatter;
use AIArmada\Addressing\Geography\Montenegro\MontenegroGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MontenegroGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Montenegrin tree with state links', function (): void {
    $this->seedCountry('ME');

    $result = app(SeedCountryGeographiesAction::class)->execute('ME');
    $country = AddressCountry::query()->where('iso2', 'ME')->firstOrFail();

    expect($result['seeded'])->toContain('ME')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(25)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(25);
});

it('formats Montenegrin addresses with the postcode left of the locality', function (): void {
    $formatted = app(MontenegroAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ul. Slobode br. 1',
        'city' => 'PODGORICA',
        'postcode' => '81000',
        'country_code' => 'ME',
    ]));

    expect($formatted)->toBe("Ul. Slobode br. 1\n81000 PODGORICA\nMontenegro");
});
it('formats Montenegrin coastal addresses with the town postcode', function (): void {
    $formatted = app(MontenegroAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jadranska magistrala 5',
        'city' => 'BAR',
        'postcode' => '85000',
        'country_code' => 'ME',
    ]));

    expect($formatted)->toBe("Jadranska magistrala 5\n85000 BAR\nMontenegro");
});
