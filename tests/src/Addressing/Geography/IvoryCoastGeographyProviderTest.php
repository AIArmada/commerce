<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastAddressFormatter;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IvoryCoastGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Ivorian tree with state links', function (): void {
    $this->seedCountry('CI');

    $result = app(SeedCountryGeographiesAction::class)->execute('CI');
    $country = AddressCountry::query()->where('iso2', 'CI')->firstOrFail();

    expect($result['seeded'])->toContain('CI')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_district')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});

it('formats Ivorian addresses without a postcode system', function (): void {
    $formatted = app(IvoryCoastAddressFormatter::class)->format(AddressData::from([
        'line1' => '06 B.P. 37',
        'city' => 'ABIDJAN',
        'country_code' => 'CI',
    ]));

    expect($formatted)->toBe("06 B.P. 37\nABIDJAN\nIvory Coast");
});
it('prints any supplied Ivorian code on its own line', function (): void {
    $formatted = app(IvoryCoastAddressFormatter::class)->format(AddressData::from([
        'line1' => '06 B.P. 37',
        'city' => 'ABIDJAN',
        'postcode' => '99999',
        'country_code' => 'CI',
    ]));

    expect($formatted)->toBe("06 B.P. 37\nABIDJAN\n99999\nIvory Coast");
});
