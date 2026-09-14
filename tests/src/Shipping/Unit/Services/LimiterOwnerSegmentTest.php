<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Unit\Services;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Shipping\Services\BatchRateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

function limiterTestOwner(string $label): User
{
    return User::query()->create([
        'name' => $label,
        'email' => Str::lower(Str::random(10)) . '@example.com',
        'password' => 'secret',
    ]);
}

describe('Batch limiter owner segmentation', function (): void {
    beforeEach(function (): void {
        config()->set('shipping.features.owner.enabled', true);
    });

    it('keeps rate budgets separate per owner', function (): void {
        $ownerA = limiterTestOwner('Limiter Owner A');
        $ownerB = limiterTestOwner('Limiter Owner B');
        $prefix = 'shipping:owner-seg-' . Str::lower(Str::random(8));

        OwnerContext::withOwner($ownerA, fn () => BatchRateLimiter::make()
            ->keyPrefix($prefix)
            ->maxAttempts(1)
            ->decaySeconds(60)
            ->execute([1], fn ($item) => $item, 'sync'));

        expect(fn () => OwnerContext::withOwner($ownerA, fn () => BatchRateLimiter::make()
            ->keyPrefix($prefix)
            ->maxAttempts(1)
            ->decaySeconds(60)
            ->maxWaitSeconds(0)
            ->execute([2], fn ($item) => $item, 'sync')))
            ->toThrow(RuntimeException::class);

        $results = OwnerContext::withOwner($ownerB, fn () => BatchRateLimiter::make()
            ->keyPrefix($prefix)
            ->maxAttempts(1)
            ->decaySeconds(60)
            ->maxWaitSeconds(0)
            ->execute([3], fn ($item) => $item, 'sync'));

        expect($results[0]['success'])->toBeTrue();
    });
});
