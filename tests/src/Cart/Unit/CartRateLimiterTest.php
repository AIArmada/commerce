<?php

declare(strict_types=1);

use AIArmada\Cart\Security\CartRateLimiter;
use AIArmada\Cart\Security\CartRateLimitResult;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    // Clear any existing rate limits before each test
    RateLimiter::clear('cart:add_item:test-user:minute');
    RateLimiter::clear('cart:add_item:test-user:hour');
    RateLimiter::clear('cart:checkout:test-user:minute');
    RateLimiter::clear('cart:checkout:test-user:hour');
    RateLimiter::clear('cart:update_item:test-user:minute');
    RateLimiter::clear('cart:update_item:test-user:hour');
});

// ============================================
// CartRateLimiter Integration Tests
// ============================================

it('allows operations within rate limits', function (): void {
    $limiter = new CartRateLimiter();

    $result = $limiter->check('test-user', 'add_item');

    expect($result->allowed)->toBeTrue();
    expect($result->operation)->toBe('add_item');
    expect($result->remainingMinute)->toBeGreaterThan(0);
    expect($result->remainingHour)->toBeGreaterThan(0);
});

it('short-circuits when rate limiting is disabled', function (): void {
    RateLimiter::shouldReceive('tooManyAttempts')->never();
    RateLimiter::shouldReceive('hit')->never();

    $limiter = new CartRateLimiter([], 'cart', false);

    $result = $limiter->check('test-user', 'add_item');

    expect($result->allowed)->toBeTrue();
    expect($result->remainingMinute)->toBe(PHP_INT_MAX);
    expect($result->remainingHour)->toBe(PHP_INT_MAX);
});

it('blocks operations when minute limit exceeded', function (): void {
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 2, 'perHour' => 100],
    ]);

    // Use up the limit
    $limiter->check('test-user', 'add_item');
    $limiter->check('test-user', 'add_item');

    // Third request should be blocked
    $result = $limiter->check('test-user', 'add_item');

    expect($result->allowed)->toBeFalse();
    expect($result->window)->toBe('minute');
    expect($result->retryAfter)->toBeGreaterThan(0);
    expect($result->limit)->toBe(2);
});

it('decrements remaining count after each check', function (): void {
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 10, 'perHour' => 100],
    ]);

    // First check
    $result1 = $limiter->check('test-user', 'add_item');
    $remaining1 = $result1->remainingMinute;

    // Second check
    $result2 = $limiter->check('test-user', 'add_item');
    $remaining2 = $result2->remainingMinute;

    expect($remaining2)->toBeLessThan($remaining1);
    expect($remaining1 - $remaining2)->toBe(1);
});

it('isolates rate limits by identifier', function (): void {
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 1, 'perHour' => 100],
    ]);

    // User 1 uses their limit
    $limiter->check('user-1', 'add_item');
    $user1Blocked = $limiter->check('user-1', 'add_item');

    // User 2 should still be allowed
    $user2Result = $limiter->check('user-2', 'add_item');

    expect($user1Blocked->allowed)->toBeFalse();
    expect($user2Result->allowed)->toBeTrue();
});

it('isolates rate limits by operation', function (): void {
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 1, 'perHour' => 100],
        'update_item' => ['perMinute' => 1, 'perHour' => 100],
    ]);

    // Use up add_item limit
    $limiter->check('test-user', 'add_item');
    $addBlocked = $limiter->check('test-user', 'add_item');

    // update_item should still be allowed
    $updateResult = $limiter->check('test-user', 'update_item');

    expect($addBlocked->allowed)->toBeFalse();
    expect($updateResult->allowed)->toBeTrue();
});

it('can clear rate limits for specific operation', function (): void {
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 1, 'perHour' => 100],
    ]);

    // Use up limit
    $limiter->check('test-user', 'add_item');
    $blocked = $limiter->check('test-user', 'add_item');
    expect($blocked->allowed)->toBeFalse();

    // Clear and try again
    $limiter->clear('test-user', 'add_item');
    $result = $limiter->check('test-user', 'add_item');

    expect($result->allowed)->toBeTrue();
});

it('uses default limits for unknown operations', function (): void {
    $limiter = new CartRateLimiter();

    $result = $limiter->check('test-user', 'unknown_operation');

    expect($result->allowed)->toBeTrue();
    expect($result->operation)->toBe('unknown_operation');
});

it('returns correct remaining attempts', function (): void {
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 10, 'perHour' => 100],
    ]);

    // Use some attempts
    $limiter->check('test-user', 'add_item');
    $limiter->check('test-user', 'add_item');
    $limiter->check('test-user', 'add_item');

    $remaining = $limiter->remaining('test-user', 'add_item');

    expect($remaining['minute'])->toBe(7);
    expect($remaining['hour'])->toBe(97);
});

it('applies trust multiplier correctly', function (): void {
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 10, 'perHour' => 100],
    ]);

    $trustedLimiter = $limiter->withTrustMultiplier(2.0);
    $limits = $trustedLimiter->getLimits();

    expect($limits['add_item']['perMinute'])->toBe(20);
    expect($limits['add_item']['perHour'])->toBe(200);
});

it('checks multiple operations and fails on first exceeded', function (): void {
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 1, 'perHour' => 100],
        'update_item' => ['perMinute' => 100, 'perHour' => 1000],
    ]);

    // Use up add_item limit
    $limiter->check('test-user', 'add_item');

    // Check multiple - should fail on add_item
    $result = $limiter->checkMultiple('test-user', ['add_item', 'update_item']);

    expect($result->allowed)->toBeFalse();
    expect($result->operation)->toBe('add_item');
});

// ============================================
// CartRateLimitResult Unit Tests
// ============================================

it('creates allowed result with correct properties', function (): void {
    $result = CartRateLimitResult::allowed('add_item', 59, 499);

    expect($result->allowed)->toBeTrue();
    expect($result->operation)->toBe('add_item');
    expect($result->remainingMinute)->toBe(59);
    expect($result->remainingHour)->toBe(499);
    expect($result->window)->toBeNull();
    expect($result->retryAfter)->toBeNull();
});

it('creates exceeded result with correct properties', function (): void {
    $result = CartRateLimitResult::exceeded('checkout', 'minute', 45, 5);

    expect($result->allowed)->toBeFalse();
    expect($result->operation)->toBe('checkout');
    expect($result->window)->toBe('minute');
    expect($result->retryAfter)->toBe(45);
    expect($result->limit)->toBe(5);
});

it('converts allowed result to array correctly', function (): void {
    $result = CartRateLimitResult::allowed('add_item', 59, 499);
    $array = $result->toArray();

    expect($array)->toHaveKeys(['allowed', 'operation', 'remaining']);
    expect($array['allowed'])->toBeTrue();
    expect($array['remaining']['minute'])->toBe(59);
    expect($array['remaining']['hour'])->toBe(499);
});

it('converts exceeded result to array correctly', function (): void {
    $result = CartRateLimitResult::exceeded('checkout', 'minute', 45, 5);
    $array = $result->toArray();

    expect($array)->toHaveKeys(['allowed', 'operation', 'window', 'retry_after', 'limit']);
    expect($array['allowed'])->toBeFalse();
    expect($array['retry_after'])->toBe(45);
});

it('provides meaningful error message when blocked', function (): void {
    $result = CartRateLimitResult::exceeded('add_item', 'minute', 45, 5);

    $message = $result->getMessage();

    expect($message)->toContain('Rate limit exceeded');
    expect($message)->toContain('add_item');
    expect($message)->toContain('minute');
});

it('provides success message when allowed', function (): void {
    $result = CartRateLimitResult::allowed('add_item', 59, 499);

    expect($result->getMessage())->toContain('allowed');
});
