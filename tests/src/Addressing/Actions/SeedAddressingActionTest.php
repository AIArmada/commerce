<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressingAction;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\SeedAddressingSummary;

beforeEach(function (): void {
    config(['addressing.geography.providers' => [SaintBarthelemyGeographyProvider::class]]);
});

it('seeds every tier for the configured providers', function (): void {
    config(['addressing.seed.full_city_countries' => ['MY']]);

    $result = app(SeedAddressingAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BL')->firstOrFail();

    expect($result)->toHaveKeys(['countries', 'references', 'states', 'cities', 'geographies'])
        ->and($result['geographies']['seeded'])->toBe(['BL'])
        ->and(AddressCountry::count())->toBeGreaterThan(200)
        ->and(State::count())->toBeGreaterThan(0)
        ->and(City::count())->toBeGreaterThan(0)
        ->and(City::query()->where('country_code', '!=', 'MY')->count())->toBe(0)
        ->and(State::query()->where('country_id', $country->getKey())->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->getKey())->where('is_active', true)->count())->toBeGreaterThan(0);
});

it('summarizes every tier on one line', function (): void {
    $line = SeedAddressingSummary::line([
        'countries' => ['created' => 1, 'updated' => 2, 'skipped' => 3],
        'references' => ['currency_links' => 4, 'timezone_links' => 5],
        'states' => ['created' => 6, 'updated' => 7, 'skipped' => 8],
        'cities' => ['created' => 9, 'updated' => 10, 'skipped' => 11],
        'geographies' => [
            'seeded' => ['MY', 'SG'],
            'skipped' => [],
            'areas' => [
                'MY' => ['created' => 12, 'updated' => 13, 'skipped' => 14],
                'SG' => ['created' => 1, 'updated' => 1, 'skipped' => 1],
            ],
        ],
    ]);

    expect($line)->toBe('Addressing seeded: countries 1 created / 2 updated / 3 skipped; country references 4 currency links / 5 timezone links; states 6 created / 7 updated / 8 skipped; cities 9 created / 10 updated / 11 skipped; country geographies seeded for MY, SG; areas 13 created / 14 updated / 15 skipped.');
});
