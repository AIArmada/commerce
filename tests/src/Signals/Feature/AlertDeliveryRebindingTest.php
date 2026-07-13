<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Support\Facades\Http;

uses(SignalsTestCase::class);

it('rejects mixed DNS answers before alert delivery transport', function (): void {
    Http::fake();
    $guard = new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34', '127.0.0.1']);

    expect(fn () => $guard->validate('https://alerts.example.test/hook'))
        ->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});
