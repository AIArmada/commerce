<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Belgium\BelgiumAddressFormatter;
use AIArmada\Addressing\Geography\Belgium\BelgiumGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BelgiumGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Belgian tree with state links', function (): void {
    $this->seedCountry('BE');

    $result = app(SeedCountryGeographiesAction::class)->execute('BE');
    $country = AddressCountry::query()->where('iso2', 'BE')->firstOrFail();

    expect($result['seeded'])->toContain('BE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(13);
});

it('formats Belgian addresses with the postcode left of the locality', function (): void {
    $formatted = app(BelgiumAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Volklorenlaan 81 bus 15',
        'city' => 'Wilrijk',
        'postcode' => '2610',
        'country_code' => 'BE',
    ]));

    expect($formatted)->toBe("Volklorenlaan 81 bus 15\n2610 Wilrijk\nBelgium");
});
it('formats Belgian addresses without a province after the town', function (): void {
    $formatted = app(BelgiumAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Loi 16',
        'city' => 'Bruxelles',
        'postcode' => '1000',
        'country_code' => 'BE',
    ]));

    expect($formatted)->toBe("Rue de la Loi 16\n1000 Bruxelles\nBelgium");
});
