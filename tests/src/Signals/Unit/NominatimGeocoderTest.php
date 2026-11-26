<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Drivers\NominatimGeocoder;
use Illuminate\Support\Facades\Http;

uses(SignalsTestCase::class);

it('rejects invalid coordinates without contacting the geocoding provider', function (): void {
    Http::fake();

    expect((new NominatimGeocoder)->reverseGeocode(91, 0))->toBeNull()
        ->and((new NominatimGeocoder)->reverseGeocode(0, INF))->toBeNull();

    Http::assertNothingSent();
});

it('rejects a successful response without address data', function (): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['error' => 'No address'], 200)]);

    expect((new NominatimGeocoder)->reverseGeocode(3.14, 101.69))->toBeNull();
});
