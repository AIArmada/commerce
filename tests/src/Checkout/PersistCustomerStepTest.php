<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\PersistCustomerStep;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;

describe('PersistCustomerStep', function (): void {
    it('creates a guest customer after payment for direct-capable checkout flows', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'cart-post-payment-guest-1',
            'selected_payment_gateway' => 'chip',
            'payment_data' => [
                'status' => 'completed',
            ],
            'billing_data' => [
                'email' => 'post-payment@example.com',
                'first_name' => 'Post',
                'last_name' => 'Payment',
                'line1' => '123 Guest Lane',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
            'shipping_data' => [
                'email' => 'post-payment@example.com',
                'name' => 'Post Payment',
                'line1' => '456 Shipping Road',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
        ]);

        $step = app(PersistCustomerStep::class);
        $step->handle($session);

        $session->refresh();
        $customer = Customer::find($session->customer_id);

        expect($customer)->not->toBeNull()
            ->and($customer->is_guest)->toBeTrue()
            ->and($customer->email)->toBe('post-payment@example.com')
            ->and($customer->addresses()->count())->toBe(2)
            ->and($session->billable?->is($customer))->toBeTrue();
    });

    it('merges a guest session customer into the authenticated customer after payment using the stored actor reference', function (): void {
        $user = User::factory()->create([
            'email' => 'registered@example.com',
        ]);

        $userCustomer = Customer::create([
            'user_id' => $user->id,
            'first_name' => 'Registered',
            'last_name' => 'User',
            'email' => 'registered@example.com',
            'is_guest' => false,
        ]);

        $guestCustomer = Customer::create([
            'first_name' => 'Guest',
            'last_name' => 'Checkout',
            'email' => 'guest@example.com',
            'is_guest' => true,
        ]);

        $guestCustomer->addresses()->create([
            'type' => 'billing',
            'line1' => '789 Merge Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country' => 'MY',
            'is_default_billing' => true,
        ]);

        $session = CheckoutSession::create([
            'cart_id' => 'cart-post-payment-merge-1',
            'selected_payment_gateway' => 'chip',
            'customer_id' => $guestCustomer->id,
            'payment_data' => [
                'status' => 'completed',
                'checkout_actor' => [
                    'type' => $user->getMorphClass(),
                    'id' => $user->id,
                ],
            ],
            'billing_data' => [
                'email' => 'registered@example.com',
                'first_name' => 'Registered',
                'last_name' => 'User',
                'line1' => '789 Merge Street',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
        ]);

        $step = app(PersistCustomerStep::class);
        $step->handle($session);

        $session->refresh();

        expect($session->customer_id)->toBe($userCustomer->id)
            ->and(Customer::query()->whereKey($guestCustomer->id)->exists())->toBeFalse()
            ->and($userCustomer->fresh()->addresses()->count())->toBe(1)
            ->and($session->billable?->is($userCustomer))->toBeTrue();
    });

    it('preserves an existing non-customer billable subject without creating a customer', function (): void {
        $user = User::factory()->create([
            'email' => 'billable@example.com',
        ]);

        $session = CheckoutSession::create([
            'cart_id' => 'cart-post-payment-billable-1',
            'selected_payment_gateway' => 'chip',
            'billable_type' => $user->getMorphClass(),
            'billable_id' => $user->id,
            'payment_data' => [
                'status' => 'completed',
            ],
            'billing_data' => [
                'email' => 'billable@example.com',
                'name' => 'Billable User',
            ],
        ]);

        $step = app(PersistCustomerStep::class);
        $step->handle($session);

        $session->refresh();

        expect($session->customer_id)->toBeNull()
            ->and($session->billable?->is($user))->toBeTrue()
            ->and(Customer::query()->where('email', 'billable@example.com')->exists())->toBeFalse();
    });

    it('creates the post-payment customer within the checkout session owner context', function (): void {
        config()->set('checkout.owner.enabled', true);
        config()->set('checkout.owner.auto_assign_on_create', true);
        config()->set('customers.features.owner.enabled', true);
        config()->set('customers.features.owner.auto_assign_on_create', true);

        $owner = User::factory()->create([
            'email' => 'owner@example.com',
        ]);

        $session = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::create([
            'cart_id' => 'cart-post-payment-owner-1',
            'selected_payment_gateway' => 'chip',
            'payment_data' => [
                'status' => 'completed',
            ],
            'billing_data' => [
                'email' => 'owner-scoped@example.com',
                'first_name' => 'Owner',
                'last_name' => 'Scoped',
                'line1' => '123 Tenant Lane',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
        ]));

        $step = app(PersistCustomerStep::class);
        $step->handle($session);

        $customer = OwnerContext::withOwner($owner, fn (): ?Customer => $session->fresh()?->customer);

        expect($customer)->not->toBeNull()
            ->and($customer?->owner_type)->toBe($owner->getMorphClass())
            ->and((string) $customer?->owner_id)->toBe((string) $owner->getKey());
    });

    it('does not reuse a guest customer from another owner context after payment', function (): void {
        config()->set('checkout.owner.enabled', true);
        config()->set('checkout.owner.auto_assign_on_create', true);
        config()->set('customers.features.owner.enabled', true);
        config()->set('customers.features.owner.auto_assign_on_create', true);

        $ownerA = User::factory()->create(['email' => 'owner-a@example.com']);
        $ownerB = User::factory()->create(['email' => 'owner-b@example.com']);

        $tenantBCustomer = OwnerContext::withOwner($ownerB, fn (): Customer => Customer::create([
            'first_name' => 'Other',
            'last_name' => 'Tenant',
            'email' => 'shared-guest@example.com',
            'is_guest' => true,
        ]));

        $session = OwnerContext::withOwner($ownerA, fn (): CheckoutSession => CheckoutSession::create([
            'cart_id' => 'cart-post-payment-cross-tenant-1',
            'selected_payment_gateway' => 'chip',
            'payment_data' => [
                'status' => 'completed',
            ],
            'billing_data' => [
                'email' => 'shared-guest@example.com',
                'first_name' => 'Owner',
                'last_name' => 'A',
                'line1' => '456 Tenant Lane',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
        ]));

        $step = app(PersistCustomerStep::class);
        $step->handle($session);

        $ownerASession = OwnerContext::withOwner($ownerA, fn (): CheckoutSession => $session->fresh(['customer']));
        $ownerBCustomerFresh = OwnerContext::withOwner($ownerB, fn (): ?Customer => $tenantBCustomer->fresh());

        expect($ownerASession->customer_id)->not->toBe($tenantBCustomer->id)
            ->and($ownerASession->customer?->email)->toBe('shared-guest@example.com')
            ->and($ownerASession->customer?->owner_type)->toBe($ownerA->getMorphClass())
            ->and((string) $ownerASession->customer?->owner_id)->toBe((string) $ownerA->getKey())
            ->and($ownerBCustomerFresh?->owner_type)->toBe($ownerB->getMorphClass())
            ->and((string) $ownerBCustomerFresh?->owner_id)->toBe((string) $ownerB->getKey());
    });
});
