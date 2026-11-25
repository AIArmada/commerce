<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Drivers\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\CommerceSupport\Support\ValidatedHttpTarget;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Http;

function pinnedResolveCurlOption(): int
{
    return defined('CURLOPT_RESOLVE') ? (int) constant('CURLOPT_RESOLVE') : 10203;
}

function pinnedTarget(bool $ipLiteral = false): ValidatedHttpTarget
{
    return new ValidatedHttpTarget(
        url: 'https://hooks.example.test/deliver',
        scheme: 'https',
        host: 'hooks.example.test',
        port: 443,
        addresses: ['93.184.216.34'],
        selectedIp: '93.184.216.34',
        isIpLiteral: $ipLiteral,
    );
}

it('sends pinned requests through the default curl-backed client', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    $response = (new PinnedHttpClient)->send('POST', pinnedTarget());

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://hooks.example.test/deliver';
    });
});

it('sends unpinned requests without transport assertions', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    $response = (new PinnedHttpClient)->send('POST', pinnedTarget(ipLiteral: true));

    expect($response->successful())->toBeTrue();
});

it('refuses pinned sends when a custom handler stack is configured', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);
    Http::globalOptions(['handler' => HandlerStack::create()]);

    try {
        expect(fn () => (new PinnedHttpClient)->send('POST', pinnedTarget()))
            ->toThrow(RuntimeException::class, 'custom Guzzle handler');

        Http::assertNothingSent();
    } finally {
        Http::globalOptions([]);
    }
});

it('refuses pinned sends with handler-swapping request options', function (): void {
    expect(fn () => (new PinnedHttpClient)->send('GET', pinnedTarget(), ['stream' => true]))
        ->toThrow(RuntimeException::class, 'custom handler options');
});

it('never lets request options enable automatic redirects', function (): void {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/'])]);

    $response = (new PinnedHttpClient)->send('GET', pinnedTarget(), ['allow_redirects' => true]);

    expect($response->status())->toBe(302);
    Http::assertSentCount(1);
});

it('refuses pinned requests configured with a streaming handler', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    expect(fn () => (new PinnedHttpClient)->send(
        'GET',
        pinnedTarget(),
        configure: static fn ($pending) => $pending->withOptions(['stream' => true]),
    ))->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});

it('rejects caller-supplied CURLOPT_RESOLVE on hostname and ip targets', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    $resolveOptions = ['curl' => [pinnedResolveCurlOption() => ['evil.test:443:127.0.0.1']]];

    expect(fn () => (new PinnedHttpClient)->send('GET', pinnedTarget(), $resolveOptions))
        ->toThrow(RuntimeException::class, 'Unsafe cURL options');

    expect(fn () => (new PinnedHttpClient)->send('GET', pinnedTarget(ipLiteral: true), $resolveOptions))
        ->toThrow(RuntimeException::class, 'Unsafe cURL options');

    Http::assertNothingSent();
});

it('rejects CURLOPT_RESOLVE injected through the configure callback', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    expect(fn () => (new PinnedHttpClient)->send(
        'GET',
        pinnedTarget(),
        configure: static fn ($pending) => $pending->withOptions([
            'curl' => [pinnedResolveCurlOption() => ['evil.test:443:127.0.0.1']],
        ]),
    ))->toThrow(RuntimeException::class, 'Unsafe cURL options');

    Http::assertNothingSent();
});

it('refuses cross-origin redirects when request option headers are set', function (): void {
    Http::fake([
        'https://example.com/start' => Http::response('', 302, ['Location' => 'https://cdn.example.com/final']),
        'https://cdn.example.com/final' => Http::response('ok', 200),
    ]);

    $guard = new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34']);

    expect(fn () => (new PinnedHttpClient)->sendFollowingRedirects(
        $guard,
        'GET',
        'https://example.com/start',
        ['headers' => ['X-Custom' => 'value']],
    ))->toThrow(InvalidArgumentException::class, 'Cross-origin redirects');
});

it('switches a 303 redirect from POST to GET without carrying the request body', function (): void {
    Http::fake([
        'https://example.com/start' => Http::response('', 303, ['Location' => '/final']),
        'https://example.com/final' => Http::response('done', 200),
    ]);

    $guard = new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34']);

    $response = (new PinnedHttpClient)->sendFollowingRedirects(
        $guard,
        'POST',
        'https://example.com/start',
        ['json' => ['a' => 1]],
    );

    expect($response->body())->toBe('done');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.com/start'
        && $request->method() === 'POST');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.com/final'
        && $request->method() === 'GET'
        && $request->body() === '');
});
