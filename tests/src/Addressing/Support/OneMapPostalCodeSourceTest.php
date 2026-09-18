<?php

declare(strict_types=1);

use AIArmada\Addressing\Support\OneMapClient;
use AIArmada\Addressing\Support\OneMapPostalCodeSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::forget('aiarmada.addressing.onemap.token');
    config()->set('addressing.onemap.email', 'test@example.com');
    config()->set('addressing.onemap.password', 'secret');
});

it('maps the first result whose postal matches the query', function (): void {
    Http::fake([
        '*/auth/post/getToken' => Http::response([
            'access_token' => 'test-token',
            'expiry_timestamp' => (string) (time() + 3 * 86400),
        ], 200),
        '*common/elastic/search*' => Http::response([
            'found' => 2,
            'totalNumPages' => 1,
            'pageNum' => 1,
            'results' => [
                ['SEARCHVAL' => '56', 'POSTAL' => '560001', 'ADDRESS' => 'Elsewhere'],
                [
                    'SEARCHVAL' => '569933',
                    'BLK_NO' => '53',
                    'ROAD_NAME' => 'ANG MO KIO AVENUE 3',
                    'BUILDING' => 'N/A',
                    'ADDRESS' => '53 ANG MO KIO AVENUE 3 SINGAPORE 569933',
                    'POSTAL' => '569933',
                    'LATITUDE' => '1.3629',
                    'LONGITUDE' => '103.8454',
                ],
            ],
        ], 200),
    ]);

    $items = (new OneMapPostalCodeSource(app(OneMapClient::class), ['569933']))->postalCodes()->all();

    expect($items)->toHaveCount(1)
        ->and($items[0]->code)->toBe('569933')
        ->and($items[0]->countryCode)->toBe('SG')
        ->and($items[0]->areaSourceId)->toBe('sg:postal-sector:56')
        ->and($items[0]->isPrimary)->toBeTrue()
        ->and($items[0]->metadata['address'])->toBe('53 ANG MO KIO AVENUE 3 SINGAPORE 569933')
        ->and($items[0]->metadata['latitude'])->toBe(1.3629);
});

it('yields nothing when OneMap has no match', function (): void {
    Http::fake([
        '*/auth/post/getToken' => Http::response([
            'access_token' => 'test-token',
            'expiry_timestamp' => (string) (time() + 3 * 86400),
        ], 200),
        '*common/elastic/search*' => Http::response([
            'found' => 0,
            'totalNumPages' => 0,
            'pageNum' => 1,
            'results' => [],
        ], 200),
    ]);

    $items = (new OneMapPostalCodeSource(app(OneMapClient::class), ['999999']))->postalCodes()->all();

    expect($items)->toBe([]);
});
