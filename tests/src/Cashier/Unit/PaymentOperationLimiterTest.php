<?php

declare(strict_types=1);

use AIArmada\Cashier\Exceptions\PaymentOperationRateLimitedException;
use AIArmada\Cashier\Gateways\Stripe\StripeSubscriptionBuilder;
use AIArmada\Cashier\Gateways\StripeGateway;
use AIArmada\Cashier\Support\PaymentOperationLimiter;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\OwnerScopedBillableUser;
use Illuminate\Support\Facades\RateLimiter;

uses(CashierTestCase::class);

beforeEach(function (): void {
    config()->set('cashier.payment_operations.rate_limiting', [
        'enabled' => true,
        'max_attempts' => 1,
        'decay_seconds' => 60,
    ]);
});

it('throws a typed exception when a payment operation exceeds the configured limit', function (): void {
    $user = new OwnerScopedBillableUser;
    $user->forceFill(['id' => 'billable-' . bin2hex(random_bytes(8))]);

    expect(PaymentOperationLimiter::run('stripe', 'charge', $user, fn (): string => 'charged'))
        ->toBe('charged');

    try {
        PaymentOperationLimiter::run('stripe', 'charge', $user, fn (): string => 'charged-again');
        $this->fail('Expected the second operation attempt to be rate limited.');
    } catch (PaymentOperationRateLimitedException $exception) {
        expect($exception->gateway())->toBe('stripe')
            ->and($exception->operation())->toBe('charge')
            ->and($exception->retryAfter())->toBeGreaterThanOrEqual(0);
    }
});

it('rate limits stripe charges before calling the gateway-native billable method', function (): void {
    $user = new OwnerScopedBillableUser;
    $user->forceFill(['id' => 'billable-' . bin2hex(random_bytes(8))]);

    RateLimiter::hit(PaymentOperationLimiter::key('stripe', 'charge', $user), 60);

    expect(fn () => (new StripeGateway)->charge($user, 1000, 'pm_card_visa'))
        ->toThrow(PaymentOperationRateLimitedException::class);
});

it('rate limits stripe refunds before calling the stripe client', function (): void {
    RateLimiter::hit(PaymentOperationLimiter::key('stripe', 'refund', 'payment:pi_rate_limited'), 60);

    expect(fn () => (new StripeGateway)->refund('pi_rate_limited'))
        ->toThrow(PaymentOperationRateLimitedException::class);
});

it('rate limits paid stripe subscription creation before calling stripe', function (): void {
    $user = new OwnerScopedBillableUser;
    $user->forceFill(['id' => 'billable-' . bin2hex(random_bytes(8))]);

    RateLimiter::hit(PaymentOperationLimiter::key('stripe', 'create_subscription', $user), 60);

    expect(fn () => (new StripeSubscriptionBuilder($user, 'default'))->create('pm_card_visa'))
        ->toThrow(PaymentOperationRateLimitedException::class);
});
