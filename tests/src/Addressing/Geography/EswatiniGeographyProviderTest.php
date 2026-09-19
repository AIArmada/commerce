<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Eswatini\EswatiniAddressFormatter;
use AIArmada\Addressing\Geography\Eswatini\EswatiniGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(EswatiniGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Eswatini tree with state links', function (): void {
    $this->seedCountry('SZ');

    $result = app(SeedCountryGeographiesAction::class)->execute('SZ');
    $country = AddressCountry::query()->where('iso2', 'SZ')->firstOrFail();

    expect($result['seeded'])->toContain('SZ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(4)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(4);
});

it('formats Eswatini addresses with the postcode below the locality', function (): void {
    $formatted = app(EswatiniAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 125',
        'city' => 'MBABANE',
        'postcode' => 'H100',
        'country_code' => 'SZ',
    ]));

    expect($formatted)->toBe("P.O. Box 125\nMBABANE\nH100\nEswatini");
});
it('prints matching Eswatini city and region once above the postcode', function (): void {
    $formatted = app(EswatiniAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 200',
        'city' => 'Manzini',
        'state' => 'Manzini',
        'postcode' => 'M200',
        'country_code' => 'SZ',
    ]));

    expect($formatted)->toBe("P.O. Box 200\nManzini\nM200\nEswatini");
});
