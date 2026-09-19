<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManAddressFormatter;
use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IsleOfManGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('sheadings')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Manx tree with state links', function (): void {
    $this->seedCountry('IM');

    $result = app(SeedCountryGeographiesAction::class)->execute('IM');
    $country = AddressCountry::query()->where('iso2', 'IM')->firstOrFail();

    expect($result['seeded'])->toContain('IM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'sheadings')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});

it('formats Manx addresses with the postcode below the post town', function (): void {
    $formatted = app(IsleOfManAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 177',
        'city' => 'DOUGLAS',
        'postcode' => 'IM99 1PS',
        'country_code' => 'IM',
    ]));

    expect($formatted)->toBe("P.O. Box 177\nDOUGLAS\nIM99 1PS\nIsle of Man");
});
it('formats Manx street addresses with the sheading below the town', function (): void {
    $formatted = app(IsleOfManAddressFormatter::class)->format(AddressData::from([
        'line1' => '50 Athol Street',
        'city' => 'Douglas',
        'state' => 'Middle',
        'postcode' => 'IM1 1JB',
        'country_code' => 'IM',
    ]));

    expect($formatted)->toBe("50 Athol Street\nDouglas\nMiddle\nIM1 1JB\nIsle of Man");
});
