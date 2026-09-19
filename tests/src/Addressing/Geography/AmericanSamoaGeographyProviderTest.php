<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\AmericanSamoa\AmericanSamoaAddressFormatter;
use AIArmada\Addressing\Geography\AmericanSamoa\AmericanSamoaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AmericanSamoaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the American Samoan tree with state links', function (): void {
    $this->seedCountry('AS');

    $result = app(SeedCountryGeographiesAction::class)->execute('AS');
    $country = AddressCountry::query()->where('iso2', 'AS')->firstOrFail();

    expect($result['seeded'])->toContain('AS')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'atoll')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});

it('formats American Samoan addresses with the US ZIP layout', function (): void {
    $formatted = app(AmericanSamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 1234',
        'city' => 'PAGO PAGO',
        'postcode' => '96799',
        'country_code' => 'AS',
    ]));

    expect($formatted)->toBe("PO BOX 1234\nPAGO PAGO AS 96799\nAmerican Samoa");
});
it('formats American Samoan ZIP+4 codes', function (): void {
    $formatted = app(AmericanSamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 999',
        'city' => 'PAGO PAGO',
        'postcode' => '96799-1234',
        'country_code' => 'AS',
    ]));

    expect($formatted)->toBe("PO BOX 999\nPAGO PAGO AS 96799-1234\nAmerican Samoa");
});
