<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\PricingServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

final class CalculatePricingStep extends AbstractCheckoutStep
{
    public function getIdentifier(): string
    {
        return 'calculate_pricing';
    }

    public function getName(): string
    {
        return 'Calculate Pricing';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['validate_cart', 'resolve_customer'];
    }

    public function handle(CheckoutSession $session): StepResult
    {
        $cartSnapshot = $session->cart_snapshot ?? [];
        $items = $cartSnapshot['items'] ?? [];

        if (empty($items)) {
            return $this->failed('No items in cart snapshot');
        }

        $pricingData = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $itemPrice = $item['price'] ?? 0;
            $quantity = $item['quantity'] ?? 1;
            $lineTotal = $itemPrice * $quantity;
            $productId = $item['product_id'] ?? data_get($item, 'attributes.product_id');

            $pricingData['items'][] = [
                'item_id' => $item['id'] ?? null,
                'product_id' => $productId,
                'unit_price' => $itemPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];

            $subtotal += $lineTotal;
        }

        // Apply any pricing rules if pricing package is available
        if ($this->hasPricingPackage()) {
            $pricingData = $this->applyPricingRules($session, $pricingData, $subtotal);
            $subtotal = $pricingData['subtotal'] ?? $subtotal;
        }

        $pricingData['subtotal'] = $subtotal;
        $pricingData['calculated_at'] = CarbonImmutable::now()->toIso8601String();

        $session->forceFill([
            'pricing_data' => $pricingData,
            'subtotal' => $subtotal,
        ]);
        $session->calculateTotals();
        $session->save();

        return $this->success('Pricing calculated', [
            'subtotal' => $subtotal,
            'item_count' => count($items),
        ]);
    }

    private function hasPricingPackage(): bool
    {
        return class_exists(PricingServiceProvider::class);
    }

    /**
     * @param  array<string, mixed>  $pricingData
     * @return array<string, mixed>
     */
    private function applyPricingRules(CheckoutSession $session, array $pricingData, int $subtotal): array
    {
        if (! interface_exists(PriceCalculatorInterface::class)) {
            $pricingData['subtotal'] = $subtotal;

            return $pricingData;
        }

        $calculator = app(PriceCalculatorInterface::class);

        $cartSnapshot = $session->cart_snapshot ?? [];
        $items = $cartSnapshot['items'] ?? [];

        $updatedItems = [];
        $newSubtotal = 0;
        $priceables = $this->preloadPriceables($items, $session);

        foreach ($items as $index => $item) {
            $quantity = (int) ($item['quantity'] ?? 1);
            $unitPrice = (int) ($item['price'] ?? 0);
            $productId = $item['product_id'] ?? data_get($item, 'attributes.product_id');

            $originalUnitPrice = null;
            $discountAmount = 0;
            $discountSource = null;
            $priceListName = null;
            $tierDescription = null;
            $promotionName = null;
            $pricingBreakdown = [];

            $priceable = $priceables[$index] ?? null;
            if ($priceable !== null) {
                $result = $calculator->calculate($priceable, $quantity, [
                    'customer_id' => $session->customer_id,
                    'currency' => $session->currency,
                ]);

                $unitPrice = $result->finalPrice;
                $originalUnitPrice = $result->originalPrice;
                $discountAmount = $result->discountAmount;
                $discountSource = $result->discountSource;
                $priceListName = $result->priceListName;
                $tierDescription = $result->tierDescription;
                $promotionName = $result->promotionName;
                $pricingBreakdown = $result->breakdown;
            }

            $lineTotal = $unitPrice * max(1, $quantity);
            $newSubtotal += $lineTotal;

            $updatedItems[] = [
                'item_id' => $item['id'] ?? null,
                'product_id' => $productId,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'original_unit_price' => $originalUnitPrice,
                'discount_amount' => $discountAmount,
                'discount_source' => $discountSource,
                'price_list' => $priceListName,
                'tier' => $tierDescription,
                'promotion' => $promotionName,
                'pricing_breakdown' => $pricingBreakdown,
            ];
        }

        $pricingData['items'] = $updatedItems;
        $pricingData['subtotal'] = $newSubtotal;

        return $pricingData;
    }

    /**
     * Batch-load priceable models — one query per associated class instead
     * of one per cart line — and validate every resolved model against the
     * session owner boundary.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, Priceable|null>
     */
    private function preloadPriceables(array $items, CheckoutSession $session): array
    {
        $priceables = [];

        /** @var array<string, array{ids: array<int, string>, indexes: array<int, int>}> $grouped */
        $grouped = [];

        foreach ($items as $index => $item) {
            $priceables[$index] = null;

            if (! is_array($item)) {
                continue;
            }

            $associated = $item['associated_model'] ?? null;

            if ($associated instanceof Priceable) {
                $priceables[$index] = $associated;

                continue;
            }

            if (! is_array($associated)) {
                continue;
            }

            $class = $associated['class'] ?? null;
            $id = $associated['id'] ?? null;

            if (! is_string($class) || $class === ''
                || (! is_string($id) && ! is_int($id))
                || ! class_exists($class)
                || ! is_subclass_of($class, Model::class)
            ) {
                continue;
            }

            $grouped[$class]['ids'][] = (string) $id;
            $grouped[$class]['indexes'][] = $index;
        }

        foreach ($grouped as $class => $group) {
            /** @var class-string<Model> $class */
            $models = $class::query()
                ->whereIn((new $class)->getKeyName(), array_values(array_unique($group['ids'])))
                ->get()
                ->keyBy(static fn (Model $model): string => (string) $model->getKey());

            foreach ($group['indexes'] as $position => $index) {
                $model = $models->get((string) $group['ids'][$position]);

                if (! $model instanceof Model) {
                    continue;
                }

                if (! $this->priceableOwnerConsistent($model, $session)) {
                    continue;
                }

                $priceables[$index] = $model instanceof Priceable ? $model : null;
            }
        }

        return $priceables;
    }

    /**
     * Defense-in-depth: reject models whose owner tuple is inconsistent
     * with the checkout session. This blocks crafted
     * associated_model.class/id payloads from resolving cross-tenant
     * records, whether or not owner mode is enabled.
     */
    private function priceableOwnerConsistent(Model $model, CheckoutSession $session): bool
    {
        $attributes = $model->getAttributes();

        if (! array_key_exists('owner_type', $attributes) || ! array_key_exists('owner_id', $attributes)) {
            return true;
        }

        $modelOwnerType = $model->getAttribute('owner_type');
        $modelOwnerId = $model->getAttribute('owner_id');

        if (($modelOwnerType === null) !== ($modelOwnerId === null)) {
            return false;
        }

        $sessionOwnerType = $session->owner_type;
        $sessionOwnerId = $session->owner_id;

        if (($sessionOwnerType === null) !== ($sessionOwnerId === null)) {
            return false;
        }

        if ($modelOwnerType === $sessionOwnerType
            && (string) ($modelOwnerId ?? '') === (string) ($sessionOwnerId ?? '')
        ) {
            return true;
        }

        $modelIsGlobal = $modelOwnerType === null && $modelOwnerId === null;

        if (! config('checkout.owner.enabled', false)) {
            // No tenant boundary is configured, so only a definite
            // cross-tenant pair (both sides owned, and different) is
            // rejected. Global catalog models stay usable either way.
            return $modelIsGlobal || ($sessionOwnerType === null && $sessionOwnerId === null);
        }

        return $modelIsGlobal && (bool) config('checkout.owner.include_global', false);
    }
}
