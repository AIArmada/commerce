<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Actions\SeedPostalCodesAction;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyGeographyProvider;
use AIArmada\Addressing\Models\AddressAreaPostalCode;
use AIArmada\Addressing\Models\PostalCode;

beforeEach(function (): void {
    config(['addressing.geography.providers' => [SaintBarthelemyGeographyProvider::class]]);
});

it('seeds postcodes and links for one country', function (): void {
    $this->seedCountry('BL');
    app(SeedCountryGeographiesAction::class)->execute('BL');

    $result = app(SeedPostalCodesAction::class)->execute('BL');

    expect($result['seeded'])->toBe(['BL'])
        ->and($result['codes']['BL'])->toBe(['created' => 1, 'updated' => 0, 'skipped' => 0, 'links' => 1])
        ->and(PostalCode::query()->where('country_code', 'BL')->count())->toBe(1)
        ->and(AddressAreaPostalCode::query()->where('source', 'bl_postal_v1')->count())->toBe(1)
        ->and(AddressAreaPostalCode::query()->where('source', 'bl_postal_v1')->value('is_primary'))->toBeTrue();
});

it('is idempotent on re-run', function (): void {
    $this->seedCountry('BL');
    app(SeedCountryGeographiesAction::class)->execute('BL');

    app(SeedPostalCodesAction::class)->execute('BL');
    $second = app(SeedPostalCodesAction::class)->execute('BL');

    expect($second['codes']['BL'])->toBe(['created' => 0, 'updated' => 0, 'skipped' => 1, 'links' => 0])
        ->and(PostalCode::query()->where('country_code', 'BL')->count())->toBe(1)
        ->and(AddressAreaPostalCode::query()->where('source', 'bl_postal_v1')->count())->toBe(1);
});

it('skips unknown country codes', function (): void {
    $result = app(SeedPostalCodesAction::class)->execute('XX');

    expect($result['seeded'])->toBe([])
        ->and($result['codes'])->toBe([])
        ->and($result['skipped'])->toContain('BL');
});

it('skips datasets without a configured provider', function (): void {
    config(['addressing.geography.providers' => []]);

    $result = app(SeedPostalCodesAction::class)->execute();

    expect($result['seeded'])->toBe([])
        ->and($result['skipped'])->toContain('BL')
        ->and(PostalCode::query()->count())->toBe(0);
});

it('covers every bundled dataset with the default providers', function (): void {
    $bundled = require __DIR__ . '/../../../../packages/addressing/config/addressing.php';
    config(['addressing.geography.providers' => $bundled['geography']['providers']]);

    $result = app(SeedPostalCodesAction::class)->execute('XX');

    $provided = [];

    foreach ($bundled['geography']['providers'] as $providerClass) {
        $provided[] = mb_strtoupper(mb_trim(app($providerClass)->countryCode()));
    }

    expect($result['seeded'])->toBe([])
        ->and($result['skipped'])->toHaveCount(163)
        ->and(array_diff($result['skipped'], $provided))->toBe([]);
});

it('throws when the country has not been seeded', function (): void {
    app(SeedPostalCodesAction::class)->execute('BL');
})->throws(InvalidArgumentException::class, 'Cannot seed postcodes for BL because the country has not been seeded.');

it('throws when areas are missing', function (): void {
    $this->seedCountry('BL');

    app(SeedPostalCodesAction::class)->execute('BL');
})->throws(InvalidArgumentException::class, 'Seed country geographies first.');
