<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Samoa\SamoaAddressFormatter;
use AIArmada\Addressing\Geography\Samoa\SamoaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SamoaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Samoan tree with state links', function (): void {
    $this->seedCountry('WS');

    $result = app(SeedCountryGeographiesAction::class)->execute('WS');
    $country = AddressCountry::query()->where('iso2', 'WS')->firstOrFail();

    expect($result['seeded'])->toContain('WS')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(11)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(11);
});

it('formats Samoan addresses with the code right of the locality', function (): void {
    $formatted = app(SamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Salenesa Street Motootua',
        'city' => 'Apia',
        'postcode' => 'WS1330',
        'country_code' => 'WS',
    ]));

    expect($formatted)->toBe("Salenesa Street Motootua\nApia WS1330\nSamoa");
});

it('formats Samoan PO box addresses with the district code', function (): void {
    $formatted = app(SamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Apia',
        'postcode' => 'WS1330',
        'country_code' => 'WS',
    ]));

    expect($formatted)->toBe("PO Box 1\nApia WS1330\nSamoa");
});
