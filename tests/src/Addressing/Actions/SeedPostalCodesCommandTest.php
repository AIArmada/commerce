<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyGeographyProvider;
use AIArmada\Addressing\Models\PostalCode;

beforeEach(function (): void {
    config(['addressing.geography.providers' => [SaintBarthelemyGeographyProvider::class]]);
    $this->seedCountry('BL');
    app(SeedCountryGeographiesAction::class)->execute('BL');
});

it('seeds postcodes for one country', function (): void {
    $this->artisan('address:seed-postal-codes', ['country' => 'BL'])->assertSuccessful();

    expect(PostalCode::query()->where('country_code', 'BL')->count())->toBe(1);
});

it('fails when no postcode datasets match', function (): void {
    $this->artisan('address:seed-postal-codes', ['country' => 'XX'])->assertFailed();
});
