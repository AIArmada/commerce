<?php

declare(strict_types=1);

use AIArmada\Cart\Jobs\AnalyzeCartForAbandonment;
use AIArmada\Cart\Jobs\ExecuteRecoveryIntervention;
use AIArmada\Cart\Jobs\WarmCartCacheJob;
use Illuminate\Contracts\Queue\ShouldQueue;

describe('AnalyzeCartForAbandonment', function (): void {
    it('can be instantiated without cart id', function (): void {
        $job = new AnalyzeCartForAbandonment;

        expect($job)->toBeInstanceOf(ShouldQueue::class)
            ->and($job->cartId)->toBeNull()
            ->and($job->batchSize)->toBe(100)
            ->and($job->ownerType)->toBeNull()
            ->and($job->ownerId)->toBeNull();
    });

    it('can be instantiated with cart id', function (): void {
        $job = new AnalyzeCartForAbandonment(cartId: 'cart-123');

        expect($job->cartId)->toBe('cart-123')
            ->and($job->ownerType)->toBeNull()
            ->and($job->ownerId)->toBeNull();
    });

    it('can be instantiated with custom batch size', function (): void {
        $job = new AnalyzeCartForAbandonment(batchSize: 50);

        expect($job->batchSize)->toBe(50)
            ->and($job->ownerType)->toBeNull()
            ->and($job->ownerId)->toBeNull();
    });

    it('returns cart-specific tags', function (): void {
        $job = new AnalyzeCartForAbandonment(cartId: 'cart-456');

        $tags = $job->tags();

        expect($tags)->toContain('cart-abandonment')
            ->and($tags)->toContain('cart:cart-456');
    });

    it('returns batch tags when no cart id', function (): void {
        $job = new AnalyzeCartForAbandonment;

        $tags = $job->tags();

        expect($tags)->toContain('cart-abandonment')
            ->and($tags)->toContain('batch');
    });

    it('has retry configuration', function (): void {
        $job = new AnalyzeCartForAbandonment;

        expect($job->tries)->toBe(3)
            ->and($job->backoff)->toBe(60);
    });
});

describe('ExecuteRecoveryIntervention', function (): void {
    it('can be instantiated', function (): void {
        $job = new ExecuteRecoveryIntervention(
            'cart-123',
            'strategy-1',
            ['type' => 'email', 'delay' => 30],
            ['probability' => 0.75]
        );

        expect($job)->toBeInstanceOf(ShouldQueue::class)
            ->and($job->cartId)->toBe('cart-123')
            ->and($job->strategyId)->toBe('strategy-1')
            ->and($job->strategy)->toBe(['type' => 'email', 'delay' => 30])
            ->and($job->prediction)->toBe(['probability' => 0.75])
            ->and($job->ownerType)->toBeNull()
            ->and($job->ownerId)->toBeNull();
    });

    it('returns correct tags', function (): void {
        $job = new ExecuteRecoveryIntervention(
            'cart-789',
            'strategy-abc',
            [],
            []
        );

        $tags = $job->tags();

        expect($tags)->toContain('cart-recovery')
            ->and($tags)->toContain('cart:cart-789')
            ->and($tags)->toContain('strategy:strategy-abc');
    });
});

describe('WarmCartCacheJob', function (): void {
    it('can be instantiated with identifier', function (): void {
        $job = new WarmCartCacheJob('user-123');

        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    it('can be instantiated with instances', function (): void {
        $job = new WarmCartCacheJob('user-456', ['default', 'wishlist']);

        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    it('has queue method', function (): void {
        $job = new WarmCartCacheJob('user-789');

        expect($job->queue())->toBeString();
    });
});
