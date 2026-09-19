<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Israel\IsraelAddressFormatter;
use AIArmada\Addressing\Geography\Israel\IsraelGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IsraelGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Israeli tree with state links', function (): void {
    $this->seedCountry('IL');

    $result = app(SeedCountryGeographiesAction::class)->execute('IL');
    $country = AddressCountry::query()->where('iso2', 'IL')->firstOrFail();

    expect($result['seeded'])->toContain('IL')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});

it('formats Israeli addresses with the 7-digit postcode left of the locality', function (): void {
    $formatted = app(IsraelAddressFormatter::class)->format(AddressData::from([
        'line1' => '16 Yafo Street',
        'city' => 'JERUSALEM',
        'postcode' => '9414219',
        'country_code' => 'IL',
    ]));

    expect($formatted)->toBe("16 Yafo Street\n9414219 JERUSALEM\nIsrael");
});
it('formats Israeli addresses passing legacy 5-digit codes through', function (): void {
    $formatted = app(IsraelAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 Rothschild Blvd',
        'city' => 'TEL AVIV',
        'postcode' => '61201',
        'country_code' => 'IL',
    ]));

    expect($formatted)->toBe("5 Rothschild Blvd\n61201 TEL AVIV\nIsrael");
});
