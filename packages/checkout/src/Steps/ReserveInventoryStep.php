<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Exceptions\InventoryException;
use AIArmada\Checkout\Integrations\InventoryAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Inventory\Data\ReservationLine;
use Throwable;

final class ReserveInventoryStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly ?InventoryAdapter $inventoryAdapter = null,
        private readonly ?CheckoutStepRegistryInterface $stepRegistry = null,
    ) {}

    public function getIdentifier(): string
    {
        return 'reserve_inventory';
    }

    public function getName(): string
    {
        return 'Reserve Inventory';
    }

    /** @return array<string> */
    public function getDependencies(): array
    {
        $deps = ['calculate_pricing'];

        if (! config('checkout.integrations.inventory.reserve_before_payment', true)) {
            $deps[] = 'process_payment';

            return $deps;
        }

        if ($this->taxStepIsEnabled()) {
            $deps[] = 'calculate_tax';
        }

        return $deps;
    }

    private function taxStepIsEnabled(): bool
    {
        if ($this->stepRegistry === null) {
            return (bool) config('checkout.steps.enabled.calculate_tax', true)
                && (bool) config('checkout.integrations.tax.enabled', false);
        }

        if (! $this->stepRegistry->has('calculate_tax')) {
            return false;
        }

        return $this->stepRegistry->isEnabled('calculate_tax');
    }

    public function canSkip(CheckoutSession $session): bool
    {
        if ($this->inventoryAdapter === null) {
            return true;
        }

        if (! config('checkout.integrations.inventory.enabled', true)) {
            return true;
        }

        if (! config('checkout.integrations.inventory.validate_stock', true)) {
            return true;
        }

        return false;
    }

    /** @return array<string, string> */
    public function validate(CheckoutSession $session): array
    {
        return [];
    }

    public function handle(CheckoutSession $session): StepResult
    {
        if ($this->inventoryAdapter === null) {
            return $this->skipped('Inventory management not available');
        }

        $cartSnapshot = $session->cart_snapshot ?? [];
        $items = $cartSnapshot['items'] ?? [];
        $reservationTtl = config('checkout.integrations.inventory.reservation_ttl', 900);
        $reference = $session->cart_id;

        try {
            $lines = [];

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? data_get($item, 'attributes.product_id');
                $variantId = $item['variant_id'] ?? data_get($item, 'attributes.variant_id');
                $quantity = $item['quantity'] ?? 1;

                if ($productId === null) {
                    continue;
                }

                $lines[] = new ReservationLine(
                    productId: $productId,
                    variantId: $variantId,
                    quantity: $quantity,
                );
            }

            if ($lines === []) {
                return $this->failed('No valid items to reserve');
            }

            $outcome = $this->inventoryAdapter->reserve(
                reference: $reference,
                lines: $lines,
                ttl: $reservationTtl,
            );

            $pricingData = $session->pricing_data ?? [];
            $pricingData['inventory_reservation'] = [
                'reference' => $outcome->reference,
                'state' => $outcome->state,
                'expires_at' => $outcome->expiresAt,
            ];
            $pricingData['reservations_expire_at'] = $outcome->expiresAt ?? now()->addSeconds($reservationTtl)->toIso8601String();

            $session->update(['pricing_data' => $pricingData]);

            return $this->success('Inventory reserved', [
                'reference' => $outcome->reference,
                'state' => $outcome->state,
                'expires_in' => $reservationTtl,
            ]);
        } catch (Throwable $e) {
            throw InventoryException::reservationFailed(
                $reference,
                $e->getMessage(),
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

        $reference = $session->cart_id;

        $this->inventoryAdapter->release($reference);

        $pricingData = $session->pricing_data ?? [];
        unset($pricingData['inventory_reservation'], $pricingData['reservations_expire_at']);
        $session->update(['pricing_data' => $pricingData]);
    }
}
