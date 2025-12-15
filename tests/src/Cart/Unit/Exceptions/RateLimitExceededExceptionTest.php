<?php

declare(strict_types=1);

use AIArmada\Cart\Exceptions\RateLimitExceededException;
use AIArmada\Cart\Security\CartRateLimitResult;

describe('RateLimitExceededException', function (): void {
    it('can be instantiated with rate limit result', function (): void {
        $result = CartRateLimitResult::exceeded(
            operation: 'add_item',
            window: '1_minute',
            retryAfter: 30,
            limit: 10
        );

        $exception = new RateLimitExceededException($result);

        expect($exception)->toBeInstanceOf(RateLimitExceededException::class)
            ->and($exception->getMessage())->not->toBeEmpty()
            ->and($exception->result)->toBe($result);
    });

    it('uses custom message when provided', function (): void {
        $result = CartRateLimitResult::exceeded(
            operation: 'update_item',
            window: '1_minute',
            retryAfter: 30,
            limit: 10
        );

        $exception = new RateLimitExceededException($result, 'Custom rate limit message');

        expect($exception->getMessage())->toBe('Custom rate limit message');
    });

    it('gets operation from result', function (): void {
        $result = CartRateLimitResult::exceeded('remove_item', '1_minute', 30, 10);
        $exception = new RateLimitExceededException($result);

        expect($exception->getOperation())->toBe('remove_item');
    });

    it('gets window from result', function (): void {
        $result = CartRateLimitResult::exceeded('add', '5_minutes', 30, 10);
        $exception = new RateLimitExceededException($result);

        expect($exception->getWindow())->toBe('5_minutes');
    });

    it('gets retry after from result', function (): void {
        $result = CartRateLimitResult::exceeded('add', '1_minute', 60, 10);
        $exception = new RateLimitExceededException($result);

        expect($exception->getRetryAfter())->toBe(60);
    });

    it('gets limit from result', function (): void {
        $result = CartRateLimitResult::exceeded('add', '1_minute', 30, 100);
        $exception = new RateLimitExceededException($result);

        expect($exception->getLimit())->toBe(100);
    });

    it('returns HTTP headers', function (): void {
        $result = CartRateLimitResult::exceeded(
            operation: 'add_item',
            window: '1_minute',
            retryAfter: 120,
            limit: 50
        );
        $exception = new RateLimitExceededException($result);

        $headers = $exception->getHeaders();

        expect($headers['X-RateLimit-Operation'])->toBe('add_item')
            ->and($headers['Retry-After'])->toBe(120)
            ->and($headers['X-RateLimit-Limit'])->toBe(50)
            ->and($headers)->toHaveKey('X-RateLimit-Reset');
    });

    it('returns minimal headers when using allowed result', function (): void {
        $result = CartRateLimitResult::allowed('test', 10, 100);
        $exception = new RateLimitExceededException($result);

        $headers = $exception->getHeaders();

        expect($headers)->toHaveKey('X-RateLimit-Operation');
    });
});
