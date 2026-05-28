<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\ResolveCustomerStep;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentCustomerData;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectContext;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectDriverInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectResolverInterface;
use AIArmada\CommerceSupport\Contracts\Payment\ResolvedPaymentSubject;
use AIArmada\Customers\Models\Customer;

use function Pest\Laravel\actingAs;

describe('ResolveCustomerStep', function (): void {
    it('creates a guest customer from billing and shipping data', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'cart-guest-1',
            'billing_data' => [
                'email' => 'guest@example.com',
                'first_name' => 'Guest',
                'last_name' => 'User',
                'line1' => '123 Guest Lane',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
            'shipping_data' => [
                'email' => 'guest@example.com',
                'name' => 'Guest User',
                'line1' => '456 Shipping Road',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
        ]);

        $step = app(ResolveCustomerStep::class);
        $step->handle($session);

        $session->refresh();
        $customer = Customer::find($session->customer_id);

        expect($customer)->not->toBeNull()
            ->and($customer->is_guest)->toBeTrue()
            ->and($customer->email)->toBe('guest@example.com')
            ->and($customer->addresses()->count())->toBe(2);
    });

    it('merges a guest customer into an authenticated customer', function (): void {
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
            'cart_id' => 'cart-merge-1',
            'customer_id' => $guestCustomer->id,
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

        actingAs($user);

        $step = app(ResolveCustomerStep::class);
        $step->handle($session);

        $session->refresh();

        expect($session->customer_id)->toBe($userCustomer->id)
            ->and(Customer::query()->whereKey($guestCustomer->id)->exists())->toBeFalse()
            ->and($userCustomer->addresses()->count())->toBe(1);
    });

    it('stores a resolved non-customer billable subject on the checkout session', function (): void {
        $user = User::factory()->create([
            'email' => 'billable@example.com',
        ]);

        app(PaymentSubjectResolverInterface::class)->register(new class($user) implements PaymentSubjectDriverInterface
        {
            public function __construct(private readonly User $user) {}

            public function getIdentifier(): string
            {
                return 'test-billable-user';
            }

            public function getPriority(): int
            {
                return 1000;
            }

            public function supports(PaymentSubjectContext $context): bool
            {
                return ($context->billingData['email'] ?? null) === $this->user->email;
            }

            public function resolve(PaymentSubjectContext $context): ?ResolvedPaymentSubject
            {
                return new ResolvedPaymentSubject(
                    subject: $this->user,
                    paymentCustomer: new PaymentCustomerData(
                        email: $this->user->email,
                        name: $this->user->name,
                    ),
                    isGuest: false,
                    resolvedBy: $this->getIdentifier(),
                );
            }
        });

        $session = CheckoutSession::create([
            'cart_id' => 'cart-billable-1',
            'billing_data' => [
                'email' => 'billable@example.com',
                'name' => $user->name,
            ],
        ]);

        $step = app(ResolveCustomerStep::class);
        $step->handle($session);

        $session->refresh();

        expect($session->customer_id)->toBeNull()
            ->and($session->billable_type)->toBe($user->getMorphClass())
            ->and($session->billable_id)->toBe($user->id)
            ->and($session->billable?->is($user))->toBeTrue();
    });
});
