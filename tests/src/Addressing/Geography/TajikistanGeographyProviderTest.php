<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanAddressFormatter;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TajikistanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Tajik tree with state links', function (): void {
    $this->seedCountry('TJ');

    $result = app(SeedCountryGeographiesAction::class)->execute('TJ');
    $country = AddressCountry::query()->where('iso2', 'TJ')->firstOrFail();

    expect($result['seeded'])->toContain('TJ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_territory')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_region')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'districts_under_republic_administration')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});

it('formats Tajik addresses with the postcode left of the locality', function (): void {
    $formatted = app(TajikistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Khoutchandi 7',
        'city' => 'GARM',
        'postcode' => '735450',
        'country_code' => 'TJ',
    ]));

    expect($formatted)->toBe("Khoutchandi 7\n735450 GARM\nTajikistan");
});
it('prints matching Tajik city and capital region once', function (): void {
    $formatted = app(TajikistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rudaki Avenue 1',
        'city' => 'DUSHANBE',
        'state' => 'Dushanbe',
        'postcode' => '734012',
        'country_code' => 'TJ',
    ]));

    expect($formatted)->toBe("Rudaki Avenue 1\n734012 DUSHANBE\nTajikistan");
});
