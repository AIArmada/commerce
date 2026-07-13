<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\CommerceSupport\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\FilamentSignals\Support\InteractionRuleScanner;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;

uses(FilamentSignalsTestCase::class);

it('rejects private page scan destinations before sending a request', function (): void {
    Http::fake();

    $scanner = new InteractionRuleScanner(
        new Filesystem,
        new PublicHttpUrlGuard(static fn (string $host): array => $host === 'private.example' ? ['10.0.0.5'] : []),
        new PinnedHttpClient,
    );

    expect(fn () => $scanner->scan('https://private.example/admin', 'click'))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('does not follow redirects while scanning public pages', function (): void {
    Http::fake([
        'https://public.example/page' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
    ]);

    $scanner = new InteractionRuleScanner(
        new Filesystem,
        new PublicHttpUrlGuard(static fn (string $host): array => $host === 'public.example' ? ['93.184.216.34'] : []),
        new PinnedHttpClient,
    );

    expect($scanner->scan('https://public.example/page', 'click'))->toBe([]);

    Http::assertSentCount(1);
});
