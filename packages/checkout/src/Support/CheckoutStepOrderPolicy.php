<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;

final readonly class CheckoutStepOrderPolicy
{
    /**
     * @param  array<string>  $configuredOrder
     * @return array<string>
     */
    public function normalizeInventoryStepOrder(
        CheckoutStepRegistryInterface $registry,
        array $configuredOrder,
    ): array {
        if ($configuredOrder === []) {
            return $configuredOrder;
        }

        $normalizedOrder = $configuredOrder;

        if (
            $registry->has('process_payment')
            && $registry->has('reserve_inventory')
            && $registry->isEnabled('process_payment')
            && $registry->isEnabled('reserve_inventory')
        ) {
            $normalizedOrder = $this->resolveInventoryStepOrder($normalizedOrder);
        }

        return $this->enforceStepDependencyOrder($normalizedOrder);
    }

    /**
     * @param  array<int, mixed>  $configuredOrder
     * @return array<string>
     */
    public function resolveInventoryStepOrder(array $configuredOrder): array
    {
        if (! in_array('reserve_inventory', $configuredOrder, true)) {
            return array_values(array_filter($configuredOrder, 'is_string'));
        }

        $order = array_values(array_filter(
            $configuredOrder,
            static fn (mixed $identifier): bool => is_string($identifier) && $identifier !== 'reserve_inventory',
        ));

        $processPaymentPosition = array_search('process_payment', $order, true);

        if ($processPaymentPosition === false) {
            return array_values(array_filter($configuredOrder, 'is_string'));
        }

        $reserveBeforePayment = config('checkout.integrations.inventory.reserve_before_payment', true);
        $inventoryPosition = $reserveBeforePayment ? $processPaymentPosition : $processPaymentPosition + 1;

        array_splice($order, $inventoryPosition, 0, ['reserve_inventory']);

        return $order;
    }

    /**
     * @param  array<int, mixed>  $configuredOrder
     * @return array<string>
     */
    public function enforceStepDependencyOrder(array $configuredOrder): array
    {
        $order = array_values(array_filter($configuredOrder, 'is_string'));

        return $this->ensureStepPrecedes($order, 'persist_customer', 'create_order');
    }

    /**
     * @param  array<string>  $order
     * @return array<string>
     */
    public function ensureStepPrecedes(array $order, string $requiredBefore, string $dependent): array
    {
        $beforePosition = array_search($requiredBefore, $order, true);
        $dependentPosition = array_search($dependent, $order, true);

        if ($beforePosition === false || $dependentPosition === false || $beforePosition < $dependentPosition) {
            return $order;
        }

        $order = array_values(array_filter(
            $order,
            static fn (string $identifier): bool => $identifier !== $requiredBefore,
        ));

        $dependentPosition = array_search($dependent, $order, true);

        if ($dependentPosition === false) {
            $order[] = $requiredBefore;

            return $order;
        }

        array_splice($order, $dependentPosition, 0, [$requiredBefore]);

        return $order;
    }
}
