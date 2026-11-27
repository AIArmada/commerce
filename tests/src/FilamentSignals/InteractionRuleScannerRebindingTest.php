<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\CommerceSupport\Drivers\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\FilamentSignals\Contracts\PageMarkupFetcher;
use AIArmada\FilamentSignals\Drivers\PinnedPageMarkupFetcher;
use AIArmada\FilamentSignals\Features\Configuration\Queries\InteractionRuleScanner;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;

uses(FilamentSignalsTestCase::class);

it('rejects mixed public and private DNS answers before scanner transport', function (): void {
    Http::fake();
    $scanner = new InteractionRuleScanner(
        new Filesystem,
        new PinnedPageMarkupFetcher(new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34', '10.0.0.7']), new PinnedHttpClient),
    );

    expect(fn () => $scanner->scan('https://mixed.example/page', 'click'))
        ->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

it('rejects oversized markup returned by a substituted fetcher', function (): void {
    $fetcher = new class implements PageMarkupFetcher
    {
        public function fetch(string $pageUrl): ?string
        {
            return '<button id="purchase">Purchase</button>' . str_repeat(' ', self::MAX_HTML_BYTES);
        }
    };

    $scanner = new InteractionRuleScanner(new Filesystem, $fetcher);

    expect($scanner->scan('https://example.test/page', 'click'))->toBe([]);
});
