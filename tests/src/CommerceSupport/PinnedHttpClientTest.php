<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\ValidatedHttpTarget;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Http;

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
