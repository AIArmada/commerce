<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CaymanIslands\CaymanIslandsAddressFormatter;
use AIArmada\Addressing\Geography\CaymanIslands\CaymanIslandsGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CaymanIslandsGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('island')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Caymanian tree with state links', function (): void {
    $this->seedCountry('KY');

    $result = app(SeedCountryGeographiesAction::class)->execute('KY');
    $country = AddressCountry::query()->where('iso2', 'KY')->firstOrFail();

    expect($result['seeded'])->toContain('KY')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'island')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(3);
});

it('formats Caymanian addresses with the postcode right of the island', function (): void {
    $formatted = app(CaymanIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 802',
        'state' => 'Grand Cayman',
        'postcode' => 'KY1-1103',
        'country_code' => 'KY',
    ]));

    expect($formatted)->toBe("P.O. Box 802\nGrand Cayman  KY1-1103\nCayman Islands");
});
it('formats Cayman Brac addresses with the Brac postcode', function (): void {
    $formatted = app(CaymanIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 12',
        'state' => 'Cayman Brac',
        'postcode' => 'KY2-2100',
        'country_code' => 'KY',
    ]));

    expect($formatted)->toBe("P.O. Box 12\nCayman Brac  KY2-2100\nCayman Islands");
});
