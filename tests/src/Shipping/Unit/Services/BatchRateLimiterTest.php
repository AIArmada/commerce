<?php

declare(strict_types=1);

use AIArmada\Shipping\Features\Shipments\Persistence\BatchRateLimiter;
use Illuminate\Support\Facades\RateLimiter;

// ============================================
// BatchRateLimiter Tests
// ============================================

beforeEach(function (): void {
    // Clear rate limiters before each test
    RateLimiter::clear('shipping:test:bulk:process');
});

describe('BatchRateLimiter', function (): void {
    it('processes all items in batch', function (): void {
        $items = [1, 2, 3, 4, 5];

        $results = BatchRateLimiter::make()
            ->keyPrefix('shipping:test')
            ->maxAttempts(100)

            ->execute($items, fn ($item) => $item * 2, 'process');

        expect($results)->toHaveCount(5);
        expect($results[0]['success'])->toBeTrue();
        expect($results[0]['result'])->toBe(2);
        expect($results[4]['result'])->toBe(10);
    });

    it('captures errors for failed items', function (): void {
        $items = [1, 2, 3];

        $results = BatchRateLimiter::make()
            ->keyPrefix('shipping:test')
            ->maxAttempts(100)
            ->execute($items, function ($item) {
                if ($item === 2) {
                    throw new RuntimeException('Item 2 failed');
                }

                return $item;
            }, 'process');

        expect($results[0]['success'])->toBeTrue();
        expect($results[1]['success'])->toBeFalse();
        expect($results[1]['error'])->toBe('Item 2 failed');
        expect($results[2]['success'])->toBeTrue();
    });

    it('preserves array keys', function (): void {
        $items = ['a' => 1, 'b' => 2, 'c' => 3];

        $results = BatchRateLimiter::make()
            ->keyPrefix('shipping:test')
            ->maxAttempts(100)
            ->execute($items, fn ($item) => $item, 'process');

        expect($results)->toHaveKeys(['a', 'b', 'c']);
    });

    it('reports excess operations without invoking their callbacks or waiting', function (): void {
        $calls = [];
        $results = BatchRateLimiter::make()
            ->keyPrefix('shipping:test:' . bin2hex(random_bytes(8)))
            ->maxAttempts(2)
            ->execute([1, 2, 3, 4], function ($item) use (&$calls) {
                $calls[] = $item;

                return $item;
            });

        expect($calls)->toBe([1, 2])
            ->and($results[2]['success'])->toBeFalse()
            ->and($results[3]['success'])->toBeFalse();
    });

    it('handles empty items gracefully', function (): void {
        $results = BatchRateLimiter::make()
            ->keyPrefix('shipping:test')
            ->execute([], fn ($item) => $item, 'process');

        expect($results)->toBeEmpty();
    });

    it('preserves iteration order', function (): void {
        $items = range(1, 10);
        $processedOrder = [];

        BatchRateLimiter::make()
            ->keyPrefix('shipping:test')
            ->maxAttempts(100)

            ->execute($items, function ($item) use (&$processedOrder) {
                $processedOrder[] = $item;

                return $item;
            }, 'process');

        expect($processedOrder)->toBe(range(1, 10));
    });
});
