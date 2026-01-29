<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Exceptions\InventoryException;
use AIArmada\Checkout\Integrations\InventoryAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use Throwable;

final class ReserveInventoryStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly ?InventoryAdapter $inventoryAdapter = null,
    ) {}

    public function getIdentifier(): string
    {
        return 'reserve_inventory';
    }

    public function getName(): string
    {
        return 'Reserve Inventory';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        $deps = ['calculate_pricing'];

        // If configured to reserve before payment, run after tax calculation
        if (config('checkout.integrations.inventory.reserve_before_payment', true)) {
            $deps[] = 'calculate_tax';
        }

        return $deps;
    }

    public function canSkip(CheckoutSession $session): bool
    {
        // Skip if inventory package is not installed or disabled
        if ($this->inventoryAdapter === null) {
            return true;
        }

        if (! config('checkout.integrations.inventory.enabled', true)) {
            return true;
        }

        // Skip if not configured to validate stock
        if (! config('checkout.integrations.inventory.validate_stock', true)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        if ($this->inventoryAdapter === null) {
            return [];
        }

        $errors = [];
        $cartSnapshot = $session->cart_snapshot ?? [];
        $items = $cartSnapshot['items'] ?? [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $variantId = $item['variant_id'] ?? null;
            $quantity = $item['quantity'] ?? 1;

            if ($productId === null) {
                continue;
            }

            $available = $this->inventoryAdapter->getAvailableStock($productId, $variantId);

            if ($available < $quantity) {
                $itemName = $item['name'] ?? "Product {$productId}";
                $errors["item_{$productId}"] = "Insufficient stock for {$itemName}: requested {$quantity}, available {$available}";
            }
        }

        return $errors;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        if ($this->inventoryAdapter === null) {
            return $this->skipped('Inventory management not available');
        }

        $cartSnapshot = $session->cart_snapshot ?? [];
        $items = $cartSnapshot['items'] ?? [];

        $reservations = [];
        $reservationTtl = config('checkout.integrations.inventory.reservation_ttl', 900);

        try {
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $variantId = $item['variant_id'] ?? null;
                $quantity = $item['quantity'] ?? 1;

                if ($productId === null) {
                    continue;
                }

                $reservation = $this->inventoryAdapter->reserve(
                    productId: $productId,
                    variantId: $variantId,
                    quantity: $quantity,
                    reference: $session->id,
                    ttl: $reservationTtl,
                );

                $reservations[] = [
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                    'reservation_id' => $reservation['id'] ?? null,
                ];
            }

            // Store reservation data in session
            $pricingData = $session->pricing_data ?? [];
            $pricingData['inventory_reservations'] = $reservations;
            $pricingData['reservations_expire_at'] = now()->addSeconds($reservationTtl)->toIso8601String();

            $session->update(['pricing_data' => $pricingData]);

            return $this->success('Inventory reserved', [
                'reservations_count' => count($reservations),
                'expires_in' => $reservationTtl,
            ]);
        } catch (Throwable $e) {
            // Release any reservations made before the failure
            foreach ($reservations as $reservation) {
                if (isset($reservation['reservation_id'])) {
                    $this->inventoryAdapter->release($reservation['reservation_id']);
                }
            }

            throw InventoryException::reservationFailed(
                $item['product_id'] ?? 'unknown',
                $e->getMessage()
            );
        }
    }

    public function rollback(CheckoutSession $session): void
    {
        if ($this->inventoryAdapter === null) {
            return;
        }

        if (! config('checkout.integrations.inventory.release_on_failure', true)) {
            return;
        }

        // Release all reservations for this checkout session at once
        $this->inventoryAdapter->releaseAllForReference($session->id);

        // Clear reservation data from session
        $pricingData = $session->pricing_data ?? [];
        unset($pricingData['inventory_reservations'], $pricingData['reservations_expire_at']);
        $session->update(['pricing_data' => $pricingData]);
    }
}
