<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Exceptions\InvalidCheckoutStateException;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;

beforeEach(function (): void {
    config()->set('checkout.owner.enabled', true);
    config()->set('checkout.owner.include_global', false);
});

afterEach(function (): void {
    OwnerContext::clearOverride();
});

it('assigns the current owner when starting checkout', function (): void {
    $owner = User::factory()->create();

    Cart::setIdentifier('checkout-owner-assignment-test');
    Cart::add('checkout-owner-assignment-sku', 'Owner Scoped Item', 1500, 1);

    $session = OwnerContext::withOwner($owner, fn () => app(CheckoutServiceInterface::class)->startCheckout(Cart::getId()));

    expect($session->owner_type)->toBe($owner->getMorphClass())
        ->and((string) $session->owner_id)->toBe((string) $owner->getKey());
});

it('prevents resuming a checkout session from another owner context', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    Cart::setIdentifier('checkout-owner-isolation-test');
    Cart::add('checkout-owner-isolation-sku', 'Tenant Isolated Item', 2200, 1);

    $session = OwnerContext::withOwner($ownerA, fn () => app(CheckoutServiceInterface::class)->startCheckout(Cart::getId()));

    OwnerContext::withOwner($ownerB, function () use ($session): void {
        expect(fn () => app(CheckoutServiceInterface::class)->resumeCheckout($session->id))
            ->toThrow(InvalidCheckoutStateException::class);
    });
});
