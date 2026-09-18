<?php

declare(strict_types=1);

use AIArmada\Addressing\Exceptions\OneMapException;
use AIArmada\Addressing\Support\OneMapClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::forget('aiarmada.addressing.onemap.token');
    config()->set('addressing.onemap.email', 'test@example.com');
    config()->set('addressing.onemap.password', 'secret');
    config()->set('addressing.onemap.retries', 1);
});

function oneMapSearchResults(): array
{
    return [
        'found' => 1,
        'totalNumPages' => 1,
        'pageNum' => 1,
        'results' => [
            [
                'SEARCHVAL' => '569933',
                'BLK_NO' => '53',
                'ROAD_NAME' => 'ANG MO KIO AVENUE 3',
                'BUILDING' => 'CHAMPION HOTEL',
                'ADDRESS' => '53 ANG MO KIO AVENUE 3 CHAMPION HOTEL SINGAPORE 569933',
                'POSTAL' => '569933',
                'X' => '30314.7932',
                'Y' => '39978.2470',
                'LATITUDE' => '1.3629',
                'LONGITUDE' => '103.8454',
            ],
        ],
    ];
}

function oneMapTokenResponse(): array
{
    return [
        'access_token' => 'test-token',
        'expiry_timestamp' => (string) (time() + 3 * 86400),
    ];
}

it('searches with a bearer token and caches it across calls', function (): void {
    $tokenCalls = 0;

    Http::fake([
        '*/auth/post/getToken' => function () use (&$tokenCalls) {
            $tokenCalls++;

            return Http::response(oneMapTokenResponse(), 200);
        },
        '*common/elastic/search*' => Http::response(oneMapSearchResults(), 200),
    ]);

    $client = app(OneMapClient::class);

    $first = $client->search('569933');
    $second = $client->search('569933');

    expect($first)->toHaveCount(1)
        ->and($first[0]['POSTAL'])->toBe('569933')
        ->and($second)->toHaveCount(1)
        ->and($tokenCalls)->toBe(1);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
        && str_contains($request->url(), 'common/elastic/search'));
});

it('refreshes the token once after a 401', function (): void {
    Http::fake([
        '*/auth/post/getToken' => Http::sequence()
            ->push(['access_token' => 'old-token', 'expiry_timestamp' => (string) (time() + 3 * 86400)], 200)
            ->push(['access_token' => 'new-token', 'expiry_timestamp' => (string) (time() + 3 * 86400)], 200),
        '*common/elastic/search*' => Http::sequence()
            ->push([], 401)
            ->push(oneMapSearchResults(), 200),
    ]);

    $results = app(OneMapClient::class)->search('569933');

    expect($results)->toHaveCount(1);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer new-token')
        && str_contains($request->url(), 'common/elastic/search'));
});

it('retries a rate-limited search', function (): void {
    Http::fake([
        '*/auth/post/getToken' => Http::response(oneMapTokenResponse(), 200),
        '*common/elastic/search*' => Http::sequence()
            ->push([], 429)
            ->push(oneMapSearchResults(), 200),
    ]);

    expect(app(OneMapClient::class)->search('569933'))->toHaveCount(1);
});

it('computes bounded retry delays', function (): void {
    expect(OneMapClient::retryDelaySeconds(0, null))->toBe(0.2)
        ->and(OneMapClient::retryDelaySeconds(1, null))->toBe(0.4)
        ->and(OneMapClient::retryDelaySeconds(0, '5'))->toBe(5.0)
        ->and(OneMapClient::retryDelaySeconds(0, '3600'))->toBe(60.0)
        ->and(OneMapClient::retryDelaySeconds(0, '0'))->toBe(0.2)
        ->and(OneMapClient::retryDelaySeconds(0, 'soon'))->toBe(0.2);
});

it('throws when credentials are missing', function (): void {
    config()->set('addressing.onemap.email', null);
    config()->set('addressing.onemap.password', null);

    Http::fake();

    expect(fn (): array => app(OneMapClient::class)->search('569933'))
        ->toThrow(OneMapException::class, 'not configured');

    Http::assertNothingSent();
});

it('throws when authentication fails', function (): void {
    Http::fake([
        '*/auth/post/getToken' => Http::response([], 401),
    ]);

    expect(fn (): array => app(OneMapClient::class)->search('569933'))
        ->toThrow(OneMapException::class, 'authentication failed');
});
