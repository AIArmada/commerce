<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ExchangeRateResolver;

describe('ExchangeRateResolver', function (): void {
    it('returns identity for the same currency', function (): void {
        expect(ExchangeRateResolver::rate('USD', [], [], 'MYR', 'MYR'))->toBe(1.0);
    });

    it('implies 1.0 for the unlisted base', function (): void {
        expect(ExchangeRateResolver::rate('USD', ['MYR' => 4.7], [], 'USD', 'MYR'))->toBe(4.7)
            ->and(ExchangeRateResolver::rate('USD', ['MYR' => 4.7], [], 'MYR', 'USD'))->toBe(1 / 4.7);
    });

    it('converts across two listed currencies', function (): void {
        expect(ExchangeRateResolver::rate('USD', ['MYR' => 4.0, 'SGD' => 2.0], [], 'MYR', 'SGD'))->toBe(0.5);
    });

    it('is case-insensitive on codes', function (): void {
        expect(ExchangeRateResolver::rate('usd', ['myr' => 4.0], [], 'usd', 'myr'))->toBe(4.0);
    });

    it('returns null for unknown, zero, and negative rates', function (): void {
        expect(ExchangeRateResolver::rate('USD', [], [], 'USD', 'MYR'))->toBeNull()
            ->and(ExchangeRateResolver::rate('USD', ['MYR' => 0], [], 'USD', 'MYR'))->toBeNull()
            ->and(ExchangeRateResolver::rate('USD', ['MYR' => -1], [], 'USD', 'MYR'))->toBeNull()
            ->and(ExchangeRateResolver::rate('USD', ['MYR' => 'nonsense'], [], 'USD', 'MYR'))->toBeNull();
    });

    it('overlays history snapshots on or before the as-of date', function (): void {
        $history = [
            '2026-01-01' => ['MYR' => 4.0],
            '2026-06-01' => ['MYR' => 5.0],
        ];

        expect(ExchangeRateResolver::rate('USD', ['MYR' => 6.0], $history, 'USD', 'MYR', new DateTimeImmutable('2026-03-01')))->toBe(4.0)
            ->and(ExchangeRateResolver::rate('USD', ['MYR' => 6.0], $history, 'USD', 'MYR', new DateTimeImmutable('2026-09-01')))->toBe(5.0)
            ->and(ExchangeRateResolver::rate('USD', ['MYR' => 6.0], $history, 'USD', 'MYR'))->toBe(6.0);
    });
});
