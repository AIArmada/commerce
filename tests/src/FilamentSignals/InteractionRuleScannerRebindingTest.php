<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\CommerceSupport\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\FilamentSignals\Support\InteractionRuleScanner;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;

uses(FilamentSignalsTestCase::class);

it('rejects mixed public and private DNS answers before scanner transport', function (): void {
    Http::fake();
    $scanner = new InteractionRuleScanner(
        new Filesystem,
        new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34', '10.0.0.7']),
        new PinnedHttpClient,
    );

    expect(fn () => $scanner->scan('https://mixed.example/page', 'click'))
        ->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});
