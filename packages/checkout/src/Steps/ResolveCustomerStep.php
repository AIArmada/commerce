<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;

final class ResolveCustomerStep extends AbstractCheckoutStep
{
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
        $customerId = $session->customer_id;

        if ($customerId === null && auth()->check()) {
            $user = auth()->user();

            if ($user !== null && method_exists($user, 'customer')) {
                /** @var \Illuminate\Database\Eloquent\Relations\Relation|null $relation */
                $relation = $user->customer();
                if ($relation !== null) {
                    $customer = $relation->getResults();
                    if ($customer !== null && isset($customer->id)) {
                        $customerId = $customer->id;
                    }
                }
            }
        }

        if ($customerId !== null) {
            $session->update(['customer_id' => $customerId]);

            $this->loadCustomerDefaults($session);

            return $this->success('Customer resolved', ['customer_id' => $customerId]);
        }

        return $this->success('Proceeding as guest checkout');
    }

    private function loadCustomerDefaults(CheckoutSession $session): void
    {
        if (! class_exists(\AIArmada\Customers\Models\Customer::class)) {
            return;
        }

        $customer = $session->customer;

        if ($customer === null) {
            return;
        }

        // Load default addresses if available
        $billingData = $session->billing_data ?? [];
        $shippingData = $session->shipping_data ?? [];

        if (empty($billingData) && method_exists($customer, 'defaultBillingAddress')) {
            $address = $customer->defaultBillingAddress();
            if ($address !== null) {
                $billingData = $address->toArray();
            }
        }

        if (empty($shippingData) && method_exists($customer, 'defaultShippingAddress')) {
            $address = $customer->defaultShippingAddress();
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
