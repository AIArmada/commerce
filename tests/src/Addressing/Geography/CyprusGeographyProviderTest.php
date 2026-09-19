<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Cyprus\CyprusAddressFormatter;
use AIArmada\Addressing\Geography\Cyprus\CyprusGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CyprusGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Cypriot tree with state links', function (): void {
    $this->seedCountry('CY');

    $result = app(SeedCountryGeographiesAction::class)->execute('CY');
    $country = AddressCountry::query()->where('iso2', 'CY')->firstOrFail();

    expect($result['seeded'])->toContain('CY')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});

it('formats Cypriot inbound addresses with the CY postcode prefix', function (): void {
    $formatted = app(CyprusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Sofochleous 26',
        'city' => 'Strovolos',
        'postcode' => 'CY-2008',
        'country_code' => 'CY',
    ]));

    expect($formatted)->toBe("Sofochleous 26\nCY-2008 Strovolos\nCyprus");
});
it('formats Cypriot domestic addresses with a bare postcode', function (): void {
    $formatted = app(CyprusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Griva Digeni 10',
        'city' => 'Larnaka',
        'postcode' => '6036',
        'country_code' => 'CY',
    ]));

    expect($formatted)->toBe("Griva Digeni 10\n6036 Larnaka\nCyprus");
});
