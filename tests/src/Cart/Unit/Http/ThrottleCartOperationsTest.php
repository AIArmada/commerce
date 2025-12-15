<?php

declare(strict_types=1);

use AIArmada\Cart\Http\Middleware\ThrottleCartOperations;
use AIArmada\Cart\Security\CartRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

describe('ThrottleCartOperations', function (): void {
    it('can be instantiated with real rate limiter', function (): void {
        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        expect($middleware)->toBeInstanceOf(ThrottleCartOperations::class);
    });

    it('allows request when rate limit is disabled', function (): void {
        config(['cart.rate_limiting.enabled' => false]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart', 'GET');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });

    it('processes request when within rate limit', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart', 'GET');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });
});
