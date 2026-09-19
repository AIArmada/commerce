<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Estonia\EstoniaAddressFormatter;
use AIArmada\Addressing\Geography\Estonia\EstoniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(EstoniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Estonian tree with state links', function (): void {
    $this->seedCountry('EE');

    $result = app(SeedCountryGeographiesAction::class)->execute('EE');
    $country = AddressCountry::query()->where('iso2', 'EE')->firstOrFail();

    expect($result['seeded'])->toContain('EE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(94)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(94)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'rural_municipality')->count())->toBe(64)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'urban_municipality')->count())->toBe(15)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(94);
});

it('formats Estonian addresses with the postcode left of the locality', function (): void {
    $formatted = app(EstoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Astri 6–1',
        'city' => 'TALLINN',
        'postcode' => '11212',
        'country_code' => 'EE',
    ]));

    expect($formatted)->toBe("Astri 6–1\n11212 TALLINN\nEstonia");
});
it('formats Estonian rural addresses with the county on the postcode line', function (): void {
    $formatted = app(EstoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Allika talu',
        'line2' => 'Halliste alevik',
        'city' => 'VILJANDIMAA',
        'postcode' => '69501',
        'country_code' => 'EE',
    ]));

    expect($formatted)->toBe("Allika talu\nHalliste alevik\n69501 VILJANDIMAA\nEstonia");
});
