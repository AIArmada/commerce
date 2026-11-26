<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\CountryGeographySeeder;
use AIArmada\Addressing\Features\PostalCodes\Models\PostalCode;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyGeographyProvider;

beforeEach(function (): void {
    config(['addressing.geography.providers' => [SaintBarthelemyGeographyProvider::class]]);
    $this->seedCountry('BL');
    app(CountryGeographySeeder::class)->execute('BL');
});

it('seeds postcodes for one country', function (): void {
    $this->artisan('address:seed-postal-codes', ['country' => 'BL'])->assertSuccessful();

    expect(PostalCode::query()->where('country_code', 'BL')->count())->toBe(1);
});

it('fails when no postcode datasets match', function (): void {
    $this->artisan('address:seed-postal-codes', ['country' => 'XX'])->assertFailed();
});
