<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\PersistCustomerStep;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
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
        $customer = OwnerContext::withOwner(null, function () use ($session): ?Customer {
            return Customer::find($session->customer_id);
        });
        $customerContactExists = OwnerContext::withOwner(null, function () use ($customer): bool {
            return $customer?->contactMethods()
                ->where('type', 'email')
                ->where('normalized_value', 'post-payment@example.com')
                ->exists() ?? false;
        });
        $customerAddressCount = OwnerContext::withOwner(null, function () use ($customer): int {
            return $customer?->addresses()->count() ?? 0;
        });
        $billableMatches = OwnerContext::withOwner(null, function () use ($session, $customer): bool {
            return $session->fresh(['billable'])?->billable?->is($customer) ?? false;
        });

        expect($customer)->not->toBeNull()
            ->and($customer->is_guest)->toBeTrue()
            ->and($customerContactExists)->toBeTrue()
            ->and($customerAddressCount)->toBe(2)
            ->and($billableMatches)->toBeTrue();
    });

    it('merges a guest session customer into the authenticated customer after payment using the stored actor reference', function (): void {
        $user = User::factory()->create([
            'email' => 'registered@example.com',
        ]);

        [$userCustomer, $guestCustomer] = OwnerContext::withOwner(null, function () use ($user): array {
            $userCustomer = Customer::create([
                'user_id' => $user->id,
                'first_name' => 'Registered',
                'last_name' => 'User',
                'email' => 'registered@example.com',
                'is_guest' => false,
            ]);
            $userCustomer->addContactMethod(ContactMethodData::email('registered@example.com'));

            $guestCustomer = Customer::create([
                'first_name' => 'Guest',
                'last_name' => 'Checkout',
                'email' => 'guest@example.com',
                'is_guest' => true,
            ]);
            $guestCustomer->addContactMethod(ContactMethodData::email('guest@example.com'));

            $guestCustomer->addresses()->create([
                'type' => 'billing',
                'line1' => '789 Merge Street',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
                'is_default_billing' => true,
            ]);

            return [$userCustomer, $guestCustomer];
        });

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
        $resolvedSession = OwnerContext::withOwner(null, function () use ($session): CheckoutSession {
            return $session->fresh(['customer', 'billable']);
        });
        $userCustomerAddressCount = OwnerContext::withOwner(null, function () use ($userCustomer): int {
            return $userCustomer->fresh()->addresses()->count();
        });
        $guestExists = OwnerContext::withOwner(null, function () use ($guestCustomer): bool {
            return Customer::query()->whereKey($guestCustomer->id)->exists();
        });

        expect($resolvedSession->customer_id)->toBe($userCustomer->id)
            ->and($guestExists)->toBeFalse()
            ->and($userCustomerAddressCount)->toBe(1)
            ->and($resolvedSession->billable?->is($userCustomer))->toBeTrue();
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
            ->and(Customer::query()->whereHas('contactMethods', function ($query): void {
                $query->where('type', 'email')
                    ->where('normalized_value', 'billable@example.com');
            })->exists())->toBeFalse();
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

        $tenantBCustomer = OwnerContext::withOwner($ownerB, function (): Customer {
            $customer = Customer::create([
                'first_name' => 'Other',
                'last_name' => 'Tenant',
                'email' => 'other-tenant@example.com',
                'is_guest' => true,
            ]);
            $customer->addContactMethod(ContactMethodData::email('shared-guest@example.com'));

            return $customer;
        });

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
        $ownerAContactExists = OwnerContext::withOwner($ownerA, function () use ($ownerASession): bool {
            return $ownerASession->customer?->contactMethods()
                ->where('type', 'email')
                ->where('normalized_value', 'shared-guest@example.com')
                ->exists() ?? false;
        });

        expect($ownerASession->customer_id)->not->toBe($tenantBCustomer->id)
            ->and($ownerAContactExists)->toBeTrue()
            ->and($ownerASession->customer?->owner_type)->toBe($ownerA->getMorphClass())
            ->and((string) $ownerASession->customer?->owner_id)->toBe((string) $ownerA->getKey())
            ->and($ownerBCustomerFresh?->owner_type)->toBe($ownerB->getMorphClass())
            ->and((string) $ownerBCustomerFresh?->owner_id)->toBe((string) $ownerB->getKey());
    });
});
