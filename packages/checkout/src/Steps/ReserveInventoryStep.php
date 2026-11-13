<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Exceptions\InventoryException;
use AIArmada\Checkout\Integrations\InventoryAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Orders\Support\CartLineSnapshot;
use Carbon\CarbonImmutable;
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
                if (! is_array($item)) {
                    continue;
                }

                $line = CartLineSnapshot::fromArray($item);

                if ($line->hasComponents()) {
                    // The composite parent is commercial identity only. Only
                    // actual component inventory is reserved, and each package
                    // quantity multiplies its per-package component quantity.
                    foreach ($line->expandedComponents() as $component) {
                        if (! $component['tracks_inventory']) {
                            continue;
                        }

                        $lines[] = new ReservationLine(
                            productId: $component['product_id'],
                            variantId: $component['variant_id'],
                            quantity: $component['quantity'],
                            inventoryableType: $component['inventoryable_type'],
                            inventoryableId: $component['inventoryable_id'],
                        );
                    }

                    continue;
                }

                if ($line->purchasableId === null) {
                    continue;
                }

                $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
                $inventoryableType = $item['inventoryable_type'] ?? $attributes['inventoryable_type'] ?? null;
                $inventoryableId = $item['inventoryable_id'] ?? $attributes['inventoryable_id'] ?? null;

                $lines[] = new ReservationLine(
                    productId: $line->productId
                        ?? (is_string($inventoryableId) || is_int($inventoryableId) ? (string) $inventoryableId : null)
                        ?? $line->purchasableId,
                    variantId: $line->variantId,
                    quantity: $line->quantity,
                    inventoryableType: is_string($inventoryableType) ? $inventoryableType : null,
                    inventoryableId: is_string($inventoryableId) || is_int($inventoryableId)
                        ? (string) $inventoryableId
                        : null,
                );
            }

            if ($lines === []) {
                return $this->skipped('No inventory-tracked items to reserve');
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
            $pricingData['reservations_expire_at'] = $outcome->expiresAt ?? CarbonImmutable::now()->addSeconds($reservationTtl)->toIso8601String();

            $session->persistState(['pricing_data' => $pricingData]);

            return $this->success('Inventory reserved', [
                'reference' => $outcome->reference,
                'state' => $outcome->state,
                'expires_in' => $reservationTtl,
            ]);
        } catch (InsufficientInventoryException $e) {
            // A short component is a shopper-facing stock change, not an
            // infrastructure fault: fail the step so compensation releases the
            // partial reservation and the shopper can review the cart.
            return $this->failed('The selected quantity is no longer available.', [
                'inventory' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            throw InventoryException::reservationFailed(
                $reference,
                $e->getMessage(),
            );
        }
    }

    public function compensate(CheckoutSession $session): StepResult
    {
        if ($this->inventoryAdapter === null) {
            return $this->compensated('Inventory adapter unavailable', [
                'operation' => 'release_inventory',
                'skipped' => true,
            ]);
        }

        if (! config('checkout.integrations.inventory.release_on_failure', true)) {
            return $this->compensated('Inventory release disabled', [
                'operation' => 'release_inventory',
                'skipped' => true,
            ]);
        }

        $reservation = data_get($session->pricing_data ?? [], 'inventory_reservation');

        if (! is_array($reservation)) {
            return $this->compensated('No inventory reservation recorded', [
                'operation' => 'release_inventory',
                'skipped' => true,
            ]);
        }

        $reference = $reservation['reference'] ?? $session->cart_id;

        if (! is_string($reference) || $reference === '') {
            return $this->failed('Inventory reservation reference is missing', [
                'reference' => 'The reservation reference must be a non-empty string',
            ]);
        }

        try {
            $outcome = $this->inventoryAdapter->release($reference);

            $pricingData = $session->pricing_data ?? [];
            unset($pricingData['inventory_reservation'], $pricingData['reservations_expire_at']);
            $session->persistState(['pricing_data' => $pricingData]);

            return $this->compensated('Inventory reservation released', [
                'operation' => 'release_inventory',
                'reference' => $reference,
                'state' => $outcome->state,
            ]);
        } catch (Throwable $e) {
            return $this->failed('Inventory reservation release failed', [
                'reference' => $reference,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
