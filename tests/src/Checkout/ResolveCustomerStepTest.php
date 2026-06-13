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
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Customers\Actions\CreateCustomer;
use AIArmada\Customers\Actions\UpdateCustomerProfile;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Payment\CustomersPaymentSubjectDriver;
use AIArmada\Customers\Services\CustomerResolver;

use function Pest\Laravel\actingAs;

describe('ResolveCustomerStep', function (): void {
    it('does not create a guest customer from billing and shipping data for direct-capable gateways', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'cart-guest-1',
            'selected_payment_gateway' => 'chip',
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

        expect($session->customer_id)->toBeNull()
            ->and(Customer::query()->whereHas('contactMethods', function ($query): void {
                $query->where('type', 'email')
                    ->where('normalized_value', 'guest@example.com');
            })->exists())->toBeFalse();
    });

    it('still creates a guest customer before payment when the gateway requires a persisted billable model', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'cart-cashier-guest-1',
            'selected_payment_gateway' => 'cashier',
            'billing_data' => [
                'email' => 'cashier-guest@example.com',
                'first_name' => 'Cashier',
                'last_name' => 'Guest',
                'line1' => '123 Guest Lane',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
            'shipping_data' => [
                'email' => 'cashier-guest@example.com',
                'name' => 'Cashier Guest',
                'line1' => '456 Shipping Road',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
        ]);

        $step = app(ResolveCustomerStep::class);
        $step->handle($session);

        $session->refresh();
        $customer = OwnerContext::withOwner(null, function () use ($session): ?Customer {
            return Customer::find($session->customer_id);
        });
        $customerContactExists = OwnerContext::withOwner(null, function () use ($customer): bool {
            return $customer?->contactMethods()
                ->where('type', 'email')
                ->where('normalized_value', 'cashier-guest@example.com')
                ->exists() ?? false;
        });
        $customerAddressCount = OwnerContext::withOwner(null, function () use ($customer): int {
            return $customer?->addresses()->count() ?? 0;
        });

        expect($customer)->not->toBeNull()
            ->and($customer->is_guest)->toBeTrue()
            ->and($customerContactExists)->toBeTrue()
            ->and($customerAddressCount)->toBe(2);
    });

    it('merges a guest customer into an authenticated customer when the gateway requires pre-payment customer materialization', function (): void {
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
            'cart_id' => 'cart-merge-1',
            'selected_payment_gateway' => 'cashier',
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
        $resolvedSession = OwnerContext::withOwner(null, function () use ($session): CheckoutSession {
            return $session->fresh(['customer']);
        });
        $userCustomerAddressCount = OwnerContext::withOwner(null, function () use ($userCustomer): int {
            return $userCustomer->fresh()->addresses()->count();
        });
        $guestExists = OwnerContext::withOwner(null, function () use ($guestCustomer): bool {
            return Customer::query()->whereKey($guestCustomer->id)->exists();
        });

        expect($resolvedSession->customer_id)->toBe($userCustomer->id)
            ->and($guestExists)->toBeFalse()
            ->and($userCustomerAddressCount)->toBe(1);
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

    it('maps address country codes onto the resolved payment customer', function (): void {
        $customer = OwnerContext::withOwner(null, function (): Customer {
            $customer = Customer::query()->create([
                'first_name' => 'Payment',
                'last_name' => 'Country',
                'email' => 'payment-country@example.com',
                'status' => 'active',
                'is_guest' => false,
            ]);
            $customer->addContactMethod(ContactMethodData::email('payment-country-' . uniqid() . '@example.com'));
            $customer->addContactMethod(ContactMethodData::phone('+60123456789', countryCode: 'MY'));

            $customer->addresses()->create([
                'type' => 'billing',
                'line1' => '123 Billing Street',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country_code' => 'SG',
                'is_default_billing' => true,
            ]);

            $customer->addresses()->create([
                'type' => 'shipping',
                'line1' => '456 Shipping Road',
                'city' => 'Sydney',
                'postcode' => '2000',
                'country_code' => 'AU',
                'is_default_shipping' => true,
            ]);

            return $customer;
        });

        $driver = new CustomersPaymentSubjectDriver(new CustomerResolver(
            new CreateCustomer,
            new UpdateCustomerProfile,
        ));

        $resolved = $driver->resolve(new PaymentSubjectContext(
            gateway: 'chip',
            subject: $customer,
            source: 'checkout.resolve_customer',
        ));

        expect($resolved)->not->toBeNull()
            ->and($resolved?->paymentCustomer?->getCustomerCountry())->toBe('SG')
            ->and($resolved?->paymentCustomer?->getBillingCountry())->toBe('SG')
            ->and($resolved?->paymentCustomer?->getShippingCountry())->toBe('AU');
    });

    it('prefers the customer email and phone columns when contact methods are stale', function (): void {
        $customer = OwnerContext::withOwner(null, function (): Customer {
            $customer = Customer::query()->create([
                'first_name' => 'Payment',
                'last_name' => 'Fallback',
                'email' => 'fresh@example.com',
                'phone' => '+60123456789',
                'status' => 'active',
                'is_guest' => false,
            ]);

            $customer->addContactMethod(ContactMethodData::email('stale@example.com'));
            $customer->addContactMethod(ContactMethodData::phone('+60987654321', countryCode: 'MY'));

            return $customer;
        });

        $driver = new CustomersPaymentSubjectDriver(new CustomerResolver(
            new CreateCustomer,
            new UpdateCustomerProfile,
        ));

        $resolved = $driver->resolve(new PaymentSubjectContext(
            gateway: 'chip',
            subject: $customer,
            source: 'checkout.resolve_customer',
        ));

        expect($resolved)->not->toBeNull()
            ->and($resolved?->paymentCustomer?->getCustomerEmail())->toBe('fresh@example.com')
            ->and($resolved?->paymentCustomer?->getCustomerPhone())->toBe('+60123456789');
    });
});
