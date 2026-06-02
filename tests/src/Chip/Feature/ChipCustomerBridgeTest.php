<?php

declare(strict_types=1);

use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Chip\Actions\LinkChipCustomerFromCheckout;
use AIArmada\Chip\ChipServiceProvider;
use AIArmada\Chip\Listeners\LinkChipCustomerFromCheckoutCompletion;
use AIArmada\Chip\Models\ChipCustomerLink;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

describe('CHIP customer bridge', function (): void {
    it('registers the checkout completion listener when checkout is installed', function (): void {
        Event::fake();

        $provider = new ChipServiceProvider(app());
        $method = new ReflectionMethod($provider, 'registerEventListeners');
        $method->setAccessible(true);
        $method->invoke($provider);

        Event::assertListening(CheckoutCompleted::class, LinkChipCustomerFromCheckoutCompletion::class);
    });

    it('links a checkout customer from a completed chip checkout payload', function (): void {
        $customer = Customer::query()->create([
            'first_name' => 'Bridge',
            'last_name' => 'Customer',
            'email' => 'bridge-customer@example.com',
        ]);

        $session = CheckoutSession::query()->create([
            'cart_id' => 'cart-chip-customer-bridge',
            'customer_id' => $customer->id,
            'payment_id' => 'purchase-chip-customer-bridge',
            'selected_payment_gateway' => 'chip',
            'cart_snapshot' => [],
            'step_states' => [],
            'payment_data' => [
                'gateway_response' => [
                    'client_id' => 'client-chip-customer-bridge',
                ],
            ],
        ]);

        app(LinkChipCustomerFromCheckoutCompletion::class)->handle(new CheckoutCompleted($session));

        $link = ChipCustomerLink::query()->sole();

        expect($link->subject?->is($customer))->toBeTrue()
            ->and($link->chip_customer_id)->toBe('client-chip-customer-bridge')
            ->and($link->metadata)->toBe([
                'source' => 'checkout_completed',
                'checkout_session_id' => $session->id,
                'chip_purchase_id' => 'purchase-chip-customer-bridge',
            ]);
    });

    it('updates the same customer link when checkout completion is replayed', function (): void {
        $customer = Customer::query()->create([
            'first_name' => 'Replay',
            'last_name' => 'Customer',
            'email' => 'replay-customer@example.com',
        ]);

        $session = CheckoutSession::query()->create([
            'cart_id' => 'cart-chip-customer-replay',
            'customer_id' => $customer->id,
            'payment_id' => 'purchase-chip-customer-replay',
            'selected_payment_gateway' => 'chip',
            'cart_snapshot' => [],
            'step_states' => [],
        ]);

        $action = app(LinkChipCustomerFromCheckout::class);

        $action->handle('purchase-chip-customer-replay', [
            'client_id' => 'client-chip-customer-first',
        ], 'first_call');

        $action->handle('purchase-chip-customer-replay', [
            'client_id' => 'client-chip-customer-second',
        ], 'second_call');

        $link = ChipCustomerLink::query()->sole();

        expect(ChipCustomerLink::query()->count())->toBe(1)
            ->and($link->subject?->is($customer))->toBeTrue()
            ->and($link->chip_customer_id)->toBe('client-chip-customer-second')
            ->and($link->metadata)->toBe([
                'source' => 'second_call',
                'checkout_session_id' => $session->id,
                'chip_purchase_id' => 'purchase-chip-customer-replay',
            ]);
    });

    it('links the exact completed checkout session when payment ids are duplicated', function (): void {
        $completedCustomer = Customer::query()->create([
            'first_name' => 'Completed',
            'last_name' => 'Customer',
            'email' => 'completed-customer@example.com',
        ]);

        $newerCustomer = Customer::query()->create([
            'first_name' => 'Newer',
            'last_name' => 'Customer',
            'email' => 'newer-customer@example.com',
        ]);

        $completedSession = CheckoutSession::query()->create([
            'cart_id' => 'cart-completed-session',
            'customer_id' => $completedCustomer->id,
            'payment_id' => 'purchase-shared-payment-id',
            'selected_payment_gateway' => 'chip',
            'cart_snapshot' => [],
            'step_states' => [],
            'payment_data' => [
                'gateway_response' => [
                    'client_id' => 'client-completed-session',
                ],
            ],
        ]);

        $newerSession = CheckoutSession::query()->create([
            'cart_id' => 'cart-newer-session',
            'customer_id' => $newerCustomer->id,
            'payment_id' => 'purchase-shared-payment-id',
            'selected_payment_gateway' => 'chip',
            'cart_snapshot' => [],
            'step_states' => [],
        ]);
        $newerSession->forceFill([
            'created_at' => $completedSession->created_at?->addMinute(),
            'updated_at' => $completedSession->updated_at?->addMinute(),
        ])->save();

        app(LinkChipCustomerFromCheckoutCompletion::class)->handle(new CheckoutCompleted($completedSession));

        $link = ChipCustomerLink::query()->sole();

        expect($link->subject?->is($completedCustomer))->toBeTrue()
            ->and($link->subject?->is($newerCustomer))->toBeFalse()
            ->and($link->metadata)->toBe([
                'source' => 'checkout_completed',
                'checkout_session_id' => $completedSession->id,
                'chip_purchase_id' => 'purchase-shared-payment-id',
            ]);
    });

    it('uses the checkout session owner context when linking an owned customer', function (): void {
        config()->set('chip.owner.enabled', true);
        config()->set('checkout.owner.enabled', true);
        config()->set('checkout.owner.auto_assign_on_create', true);
        config()->set('customers.features.owner.enabled', true);
        config()->set('customers.features.owner.auto_assign_on_create', true);

        $owner = User::query()->create([
            'name' => 'Bridge Owner',
            'email' => 'bridge-owner@example.com',
            'password' => 'secret',
        ]);

        [$customer, $session] = OwnerContext::withOwner($owner, function (): array {
            $customer = Customer::query()->create([
                'first_name' => 'Owned',
                'last_name' => 'Customer',
                'email' => 'owned-customer@example.com',
            ]);

            $session = CheckoutSession::query()->create([
                'cart_id' => 'cart-owned-customer-bridge',
                'customer_id' => $customer->id,
                'payment_id' => 'purchase-owned-customer-bridge',
                'selected_payment_gateway' => 'chip',
                'cart_snapshot' => [],
                'step_states' => [],
                'payment_data' => [
                    'gateway_response' => [
                        'client_id' => 'client-owned-customer-bridge',
                    ],
                ],
            ]);

            return [$customer, $session];
        });

        app(LinkChipCustomerFromCheckoutCompletion::class)->handle(new CheckoutCompleted($session));

        $link = ChipCustomerLink::query()
            ->withoutOwnerScope()
            ->sole();

        $linkSubjectMatches = OwnerContext::withOwner($owner, fn (): bool => $link->subject?->is($customer) === true);

        expect($linkSubjectMatches)->toBeTrue()
            ->and($link->chip_customer_id)->toBe('client-owned-customer-bridge')
            ->and($link->owner_type)->toBe($owner->getMorphClass())
            ->and($link->owner_id)->toBe((string) $owner->getKey());
    });

    it('skips owned checkout sessions when the owner cannot be resolved', function (): void {
        $customer = Customer::query()->create([
            'first_name' => 'Missing',
            'last_name' => 'Owner',
            'email' => 'missing-owner@example.com',
        ]);

        $session = CheckoutSession::query()->create([
            'cart_id' => 'cart-missing-owner',
            'customer_id' => $customer->id,
            'payment_id' => 'purchase-missing-owner',
            'selected_payment_gateway' => 'chip',
            'owner_type' => User::class,
            'owner_id' => (string) Str::uuid(),
            'cart_snapshot' => [],
            'step_states' => [],
            'payment_data' => [
                'gateway_response' => [
                    'client_id' => 'client-missing-owner',
                ],
            ],
        ]);

        app(LinkChipCustomerFromCheckoutCompletion::class)->handle(new CheckoutCompleted($session));

        expect(ChipCustomerLink::query()->count())->toBe(0);
    });

    it('ignores completed checkout sessions for other payment gateways', function (): void {
        $customer = Customer::query()->create([
            'first_name' => 'Other',
            'last_name' => 'Gateway',
            'email' => 'other-gateway@example.com',
        ]);

        $session = CheckoutSession::query()->create([
            'cart_id' => 'cart-other-gateway',
            'customer_id' => $customer->id,
            'payment_id' => 'purchase-other-gateway',
            'selected_payment_gateway' => 'cashier',
            'cart_snapshot' => [],
            'step_states' => [],
            'payment_data' => [
                'gateway_response' => [
                    'client_id' => 'client-other-gateway',
                ],
            ],
        ]);

        app(LinkChipCustomerFromCheckoutCompletion::class)->handle(new CheckoutCompleted($session));

        expect(ChipCustomerLink::query()->count())->toBe(0);
    });
});
