<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ResolveSingaporePostalCodesAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\PostalCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::forget('aiarmada.addressing.onemap.token');
    config()->set('addressing.onemap.email', 'test@example.com');
    config()->set('addressing.onemap.password', 'secret');
});

function fakeOneMap(array $resultsByPostcode): void
{
    Http::fake([
        '*/auth/post/getToken' => Http::response([
            'access_token' => 'test-token',
            'expiry_timestamp' => (string) (time() + 3 * 86400),
        ], 200),
        '*common/elastic/search*' => function ($request) use ($resultsByPostcode) {
            $query = (string) $request->data()['searchVal'];
            $results = $resultsByPostcode[$query] ?? [];

            return Http::response([
                'found' => count($results),
                'totalNumPages' => 1,
                'pageNum' => 1,
                'results' => $results,
            ], 200);
        },
    ]);
}

function oneMapResult(string $postcode, string $address = '53 ANG MO KIO AVENUE 3 SINGAPORE 569933'): array
{
    return [
        'SEARCHVAL' => $postcode,
        'BLK_NO' => '53',
        'ROAD_NAME' => 'ANG MO KIO AVENUE 3',
        'BUILDING' => 'N/A',
        'ADDRESS' => $address,
        'POSTAL' => $postcode,
        'LATITUDE' => '1.3629',
        'LONGITUDE' => '103.8454',
    ];
}

it('serves cached postcodes without calling OneMap', function (): void {
    Http::fake();
    PostalCode::query()->create([
        'country_code' => 'SG',
        'code' => '569933',
        'is_active' => true,
    ]);

    $result = app(ResolveSingaporePostalCodesAction::class)->execute(['569933']);

    expect($result->resolved)->toBe(['569933'])
        ->and($result->invalid)->toBe([]);

    Http::assertNothingSent();
});

it('imports missing postcodes from OneMap and links their sector', function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    app(SeedCountryGeographiesAction::class)->execute('SG');
    fakeOneMap(['569933' => [oneMapResult('569933')]]);

    $result = app(ResolveSingaporePostalCodesAction::class)->execute(['569933']);

    $postalCode = PostalCode::query()->where('country_code', 'SG')->where('code', '569933')->firstOrFail();
    $sector = AddressArea::query()->where('source_id', 'sg:postal-sector:56')->firstOrFail();

    expect($result->resolved)->toBe(['569933'])
        ->and($result->invalid)->toBe([])
        ->and($postalCode->areas()->whereKey($sector->getKey())->exists())->toBeTrue()
        ->and($postalCode->metadata['address'] ?? null)->toBe('53 ANG MO KIO AVENUE 3 SINGAPORE 569933');
});

it('reports malformed postcodes as invalid without calling OneMap', function (): void {
    Http::fake();

    $result = app(ResolveSingaporePostalCodesAction::class)->execute(['ABC', '12345', '']);

    expect($result->resolved)->toBe([])
        ->and($result->invalid)->toBe(['ABC', '12345']);

    Http::assertNothingSent();
});

it('reports postcodes OneMap does not know as invalid', function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    app(SeedCountryGeographiesAction::class)->execute('SG');
    fakeOneMap([]);

    $result = app(ResolveSingaporePostalCodesAction::class)->execute(['999999']);

    expect($result->resolved)->toBe([])
        ->and($result->invalid)->toBe(['999999'])
        ->and(PostalCode::query()->where('country_code', 'SG')->where('code', '999999')->exists())->toBeFalse();
});

it('fails fast when the sector tree is not seeded', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SG')->firstOrFail();

    expect(AddressArea::query()->where('country_id', $country->id)->exists())->toBeFalse();

    fakeOneMap(['569933' => [oneMapResult('569933')]]);

    expect(fn (): mixed => app(ResolveSingaporePostalCodesAction::class)->execute(['569933']))
        ->toThrow(InvalidArgumentException::class, 'Area not found');
});
