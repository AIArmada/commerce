<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectContext;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;

final class ResolveCustomerStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly PaymentSubjectResolverInterface $paymentSubjectResolver,
    ) {}

    public function getIdentifier(): string
    {
        return 'resolve_customer';
    }

    public function getName(): string
    {
        return 'Resolve Customer';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['validate_cart'];
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        $errors = [];

        // Customer can be resolved from session, authenticated user, or guest checkout
        // This step allows guest checkout by default when no customer_id is set

        return $errors;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        $owner = $session->hasOwner() ? $session->owner : null;

        return OwnerContext::withOwner($owner, function () use ($owner, $session): StepResult {
            $customer = $session->customer_id !== null ? $session->customer : null;
            $billable = $session->billable;
            $billingData = $session->billing_data ?? [];
            $shippingData = $session->shipping_data ?? [];
            $user = auth()->check() ? auth()->user() : null;

            if ($user instanceof Model) {
                $this->storeCheckoutActorReference($session, $user);
            }

            $resolved = $this->paymentSubjectResolver->resolve(new PaymentSubjectContext(
                gateway: $session->selected_payment_gateway ?? (string) config('checkout.payment.default_gateway', 'chip'),
                actor: $user instanceof Model ? $user : null,
                sessionCustomer: $customer,
                sessionBillable: $billable,
                billingData: $billingData,
                shippingData: $shippingData,
                metadata: ['checkout_session_id' => $session->id],
                owner: $owner,
                source: 'checkout.resolve_customer',
            ));

            if ($resolved !== null && $resolved->subject instanceof Model) {
                $updates = [
                    'billable_type' => $resolved->subject->getMorphClass(),
                    'billable_id' => (string) $resolved->subject->getKey(),
                ];

                if ($resolved->subject instanceof Customer) {
                    $updates['customer_id'] = $resolved->subject->id;
                }

                $session->update($updates);
                $session->unsetRelation('customer');
                $session->unsetRelation('billable');

                if ($resolved->subject instanceof Customer) {
                    $this->loadCustomerDefaults($session);
                }

                return $this->success('Payment subject resolved', [
                    'customer_id' => $updates['customer_id'] ?? null,
                    'billable_type' => $updates['billable_type'],
                    'billable_id' => $updates['billable_id'],
                ]);
            }

            if ($customer !== null) {
                $session->update([
                    'customer_id' => $customer->id,
                    'billable_type' => $customer->getMorphClass(),
                    'billable_id' => (string) $customer->getKey(),
                ]);
                $session->unsetRelation('customer');
                $session->unsetRelation('billable');

                $this->loadCustomerDefaults($session);

                return $this->success('Customer resolved', [
                    'customer_id' => $customer->id,
                    'billable_type' => $customer->getMorphClass(),
                    'billable_id' => (string) $customer->getKey(),
                ]);
            }

            if ($billable instanceof Model) {
                return $this->success('Billable already resolved', [
                    'billable_type' => $billable->getMorphClass(),
                    'billable_id' => (string) $billable->getKey(),
                ]);
            }

            return $this->success('Proceeding as guest checkout');
        });
    }

    private function storeCheckoutActorReference(CheckoutSession $session, Model $user): void
    {
        $actorId = $user->getKey();

        if ($actorId === null) {
            return;
        }

        $paymentData = $session->payment_data ?? [];
        $actorReference = [
            'type' => $user->getMorphClass(),
            'id' => (string) $actorId,
        ];

        if (($paymentData['checkout_actor'] ?? null) === $actorReference) {
            return;
        }

        $paymentData['checkout_actor'] = $actorReference;

        $session->update(['payment_data' => $paymentData]);
    }

    private function loadCustomerDefaults(CheckoutSession $session): void
    {
        if (! class_exists(Customer::class)) {
            return;
        }

        $customer = $session->customer;

        if ($customer === null) {
            return;
        }

        // Load default addresses if available
        $billingData = $session->billing_data ?? [];
        $shippingData = $session->shipping_data ?? [];

        if (empty($billingData) && method_exists($customer, 'getDefaultBillingAddress')) {
            $address = $customer->getDefaultBillingAddress();
            if ($address !== null) {
                $billingData = $address->toArray();
            }
        }

        if (empty($shippingData) && method_exists($customer, 'getDefaultShippingAddress')) {
            $address = $customer->getDefaultShippingAddress();
            if ($address !== null) {
                $shippingData = $address->toArray();
            }
        }

        $session->update([
            'billing_data' => $billingData,
            'shipping_data' => $shippingData,
        ]);
    }
}
