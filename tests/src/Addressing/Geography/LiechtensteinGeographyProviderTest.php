<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Liechtenstein\LiechtensteinAddressFormatter;
use AIArmada\Addressing\Geography\Liechtenstein\LiechtensteinGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LiechtensteinGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('commune')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Liechtensteiner tree with state links', function (): void {
    $this->seedCountry('LI');

    $result = app(SeedCountryGeographiesAction::class)->execute('LI');
    $country = AddressCountry::query()->where('iso2', 'LI')->firstOrFail();

    expect($result['seeded'])->toContain('LI')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'commune')->count())->toBe(11)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(11);
});

it('formats Liechtenstein addresses with the postcode left of the locality', function (): void {
    $formatted = app(LiechtensteinAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Städtle 37',
        'city' => 'Vaduz',
        'postcode' => '9490',
        'country_code' => 'LI',
    ]));

    expect($formatted)->toBe("Städtle 37\n9490 Vaduz\nLiechtenstein");
});
it('formats Liechtenstein addresses passing LI prefixes through', function (): void {
    $formatted = app(LiechtensteinAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Poststrasse 1',
        'city' => 'Schaan',
        'postcode' => 'LI-9494',
        'country_code' => 'LI',
    ]));

    expect($formatted)->toBe("Poststrasse 1\nLI-9494 Schaan\nLiechtenstein");
});
