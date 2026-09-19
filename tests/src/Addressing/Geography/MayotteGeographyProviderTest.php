<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mayotte\MayotteAddressFormatter;
use AIArmada\Addressing\Geography\Mayotte\MayotteGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MayotteGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('commune')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Mahoran tree with state links', function (): void {
    $this->seedCountry('YT');

    $result = app(SeedCountryGeographiesAction::class)->execute('YT');
    $country = AddressCountry::query()->where('iso2', 'YT')->firstOrFail();

    expect($result['seeded'])->toContain('YT')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'commune')->count())->toBe(17)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(17);
});

it('formats Mahoran addresses with the code left of the locality', function (): void {
    $formatted = app(MayotteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Mairie',
        'city' => 'MAMOUDZOU',
        'postcode' => '97600',
        'country_code' => 'YT',
    ]));

    expect($formatted)->toBe("Rue de la Mairie\n97600 MAMOUDZOU\nMayotte");
});

it('formats Chirongui addresses with their own code', function (): void {
    $formatted = app(MayotteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 21',
        'city' => 'CHIRONGUI',
        'postcode' => '97620',
        'country_code' => 'YT',
    ]));

    expect($formatted)->toBe("BP 21\n97620 CHIRONGUI\nMayotte");
});
