<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Support\SiteContentFetcher;
use AIArmada\CommerceSupport\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Support\Facades\Http;

it('rejects mixed DNS answers before affiliate site scanning transport', function (): void {
    Http::fake();
    $fetcher = new SiteContentFetcher(
        new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34', '192.168.1.20']),
        new PinnedHttpClient,
    );

    expect($fetcher->fetch('mixed.example', '/affiliate'))->toBeNull();
    Http::assertNothingSent();
});

it('keeps non-success status handling explicit and falls back from https to http', function (): void {
    Http::fake([
        'https://93.184.216.34/*' => Http::response('unavailable', 503),
        'http://93.184.216.34/*' => Http::response('<html>affiliate</html>', 200),
    ]);
    $fetcher = new SiteContentFetcher(
        new PublicHttpUrlGuard,
        new PinnedHttpClient,
    );

    expect($fetcher->fetch('93.184.216.34', '/affiliate'))->toBe('<html>affiliate</html>');
    Http::assertSentCount(2);
});
