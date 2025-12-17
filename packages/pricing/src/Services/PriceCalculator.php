<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Services;

use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Pricing\Models\Promotion;
use AIArmada\Pricing\Support\PricingOwnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class PriceCalculator
{
    /**
     * Calculate the final price for a priceable item.
     *
     * @param  array<string, mixed>  $context
     */
    public function calculate(Priceable $item, int $quantity = 1, array $context = []): PriceResultData
    {
        $quantity = max(1, $quantity);
        $basePrice = $item->getBasePrice();
        $breakdown = [];

        $priceableType = $this->getPriceableMorphType($item);
        $priceableId = $item->getBuyableIdentifier();

        // 1. Check for customer-specific price
        $customerPrice = $this->getCustomerPrice($priceableType, $priceableId, $quantity, $context);
        if ($customerPrice !== null) {
            $breakdown[] = ['type' => 'customer_specific', 'price' => $customerPrice];

            return $this->buildResult($basePrice, $customerPrice, 'Customer Specific Price', $breakdown);
        }

        // 2. Check for segment price
        $segmentPrice = $this->getSegmentPrice($priceableType, $priceableId, $quantity, $context);
        if ($segmentPrice !== null) {
            $breakdown[] = ['type' => 'segment', 'price' => $segmentPrice];

            return $this->buildResult($basePrice, $segmentPrice, 'Segment Price', $breakdown);
        }

        // 3. Check for tier pricing
        $tierResult = $this->getTierPrice($priceableType, $priceableId, $quantity, $context);
        if ($tierResult !== null) {
            $breakdown[] = ['type' => 'tier', 'price' => $tierResult['price'], 'tier' => $tierResult['tier']];

            return $this->buildResult(
                $basePrice,
                $tierResult['price'],
                'Tier Pricing',
                $breakdown,
                tierDescription: $tierResult['tier']
            );
        }

        // 4. Check for active promotions
        $promotionResult = $this->getPromotionPrice($priceableType, $priceableId, $basePrice, $quantity);
        if ($promotionResult !== null) {
            $breakdown[] = ['type' => 'promotion', 'price' => $promotionResult['price'], 'promotion' => $promotionResult['name']];

            return $this->buildResult(
                $basePrice,
                $promotionResult['price'],
                'Promotion',
                $breakdown,
                promotionName: $promotionResult['name']
            );
        }

        // 5. Check for price list price
        $priceListResult = $this->getPriceListPrice($priceableType, $priceableId, $quantity, $context);
        if ($priceListResult !== null) {
            $breakdown[] = ['type' => 'price_list', 'price' => $priceListResult['price'], 'list' => $priceListResult['name']];

            return $this->buildResult(
                $basePrice,
                $priceListResult['price'],
                'Price List',
                $breakdown,
                priceListName: $priceListResult['name']
            );
        }

        // 6. Return base price
        $breakdown[] = ['type' => 'base', 'price' => $basePrice];

        return $this->buildResult($basePrice, $basePrice, null, $breakdown);
    }

    /**
     * Resolve the morph type used for polymorphic relations.
     */
    protected function getPriceableMorphType(Priceable $item): string
    {
        if ($item instanceof Model) {
            return $item->getMorphClass();
        }

        return get_class($item);
    }

    /**
     * Get customer-specific price.
     *
     * @param  array<string, mixed>  $context
     */
    protected function getCustomerPrice(string $priceableType, string $priceableId, int $quantity, array $context): ?int
    {
        $customerId = Arr::get($context, 'customer_id');

        if (! is_string($customerId) || $customerId === '') {
            return null;
        }

        // Look up customer-specific pricing
        $price = PricingOwnerScope::applyToOwnedQuery(Price::query())
            ->where('priceable_type', $priceableType)
            ->where('priceable_id', $priceableId)
            ->forQuantity($quantity)
            ->whereIn(
                'price_list_id',
                PricingOwnerScope::applyToOwnedQuery(PriceList::query())
                    ->active()
                    ->where('customer_id', $customerId)
                    ->select('id')
            )
            ->active()
            ->orderByDesc('min_quantity')
            ->first();

        return $price?->amount;
    }

    /**
     * Get segment-based price.
     *
     * @param  array<string, mixed>  $context
     */
    protected function getSegmentPrice(string $priceableType, string $priceableId, int $quantity, array $context): ?int
    {
        $segmentIds = Arr::get($context, 'segment_ids');

        if (! is_array($segmentIds) || $segmentIds === []) {
            return null;
        }

        $price = PricingOwnerScope::applyToOwnedQuery(Price::query())
            ->where('priceable_type', $priceableType)
            ->where('priceable_id', $priceableId)
            ->forQuantity($quantity)
            ->whereIn(
                'price_list_id',
                PricingOwnerScope::applyToOwnedQuery(PriceList::query())
                    ->active()
                    ->whereIn('segment_id', $segmentIds)
                    ->select('id')
            )
            ->active()
            ->orderBy('amount', 'asc') // Best price
            ->orderByDesc('min_quantity')
            ->first();

        return $price?->amount;
    }

    /**
     * Get tier-based price for quantity.
     *
     * @return array{price: int, tier: string}|null
     */
    protected function getTierPrice(string $tierableType, string $tierableId, int $quantity, array $context): ?array
    {
        if ($quantity <= 1) {
            return null;
        }

        $priceListId = Arr::get($context, 'price_list_id');

        $query = PricingOwnerScope::applyToOwnedQuery(PriceTier::query())
            ->where('tierable_type', $tierableType)
            ->where('tierable_id', $tierableId)
            ->forQuantity($quantity)
            ->when(is_string($priceListId) && $priceListId !== '', function ($q) use ($priceListId): void {
                $q->where(function ($inner) use ($priceListId): void {
                    $inner->where('price_list_id', $priceListId)->orWhereNull('price_list_id');
                })->orderByRaw('CASE WHEN price_list_id IS NULL THEN 1 ELSE 0 END');
            }, function ($q): void {
                $q->whereNull('price_list_id');
            });

        $tier = $query->orderBy('min_quantity', 'desc')->first();

        if (! $tier) {
            return null;
        }

        return [
            'price' => $tier->amount,
            'tier' => $tier->getDescription(),
        ];
    }

    /**
     * Get promotional price.
     *
     * @return array{price: int, name: string}|null
     */
    protected function getPromotionPrice(string $promotionableType, string $promotionableId, int $basePrice, int $quantity): ?array
    {
        $promotionTable = (new Promotion)->getTable();
        $promotionablesTable = config('pricing.database.tables.promotionables', 'promotionables');

        $promotion = PricingOwnerScope::applyToOwnedQuery(Promotion::query())
            ->active()
            ->whereExists(function ($query) use ($promotionTable, $promotionablesTable, $promotionableType, $promotionableId): void {
                $query->selectRaw('1')
                    ->from($promotionablesTable)
                    ->whereColumn("{$promotionablesTable}.promotion_id", "{$promotionTable}.id")
                    ->where("{$promotionablesTable}.promotionable_type", $promotionableType)
                    ->where("{$promotionablesTable}.promotionable_id", $promotionableId);
            })
            ->orderBy('priority', 'desc')
            ->first();

        if (! $promotion) {
            return null;
        }

        if ($promotion->min_quantity !== null && $quantity < $promotion->min_quantity) {
            return null;
        }

        if ($promotion->min_purchase_amount !== null && ($basePrice * $quantity) < $promotion->min_purchase_amount) {
            return null;
        }

        $discount = $promotion->calculateDiscount($basePrice);
        $finalPrice = max(0, $basePrice - $discount);

        return [
            'price' => $finalPrice,
            'name' => $promotion->name,
        ];
    }

    /**
     * Get price from price list.
     *
     * @param  array<string, mixed>  $context
     * @return array{price: int, name: string}|null
     */
    protected function getPriceListPrice(string $priceableType, string $priceableId, int $quantity, array $context): ?array
    {
        $priceListId = Arr::get($context, 'price_list_id');

        $priceListQuery = PricingOwnerScope::applyToOwnedQuery(PriceList::query())->active();

        $priceList = is_string($priceListId) && $priceListId !== ''
            ? $priceListQuery->whereKey($priceListId)->first()
            : $priceListQuery->default()->orderByDesc('priority')->first();

        if (! $priceList) {
            return null;
        }

        $price = PricingOwnerScope::applyToOwnedQuery(Price::query())
            ->where('price_list_id', $priceList->id)
            ->where('priceable_type', $priceableType)
            ->where('priceable_id', $priceableId)
            ->forQuantity($quantity)
            ->active()
            ->orderByDesc('min_quantity')
            ->first();

        if (! $price) {
            return null;
        }

        return [
            'price' => $price->amount,
            'name' => $priceList->name,
        ];
    }

    /**
     * Build the price result.
     *
     * @param  array<int, array<string, mixed>>  $breakdown
     */
    protected function buildResult(
        int $originalPrice,
        int $finalPrice,
        ?string $discountSource,
        array $breakdown,
        ?string $priceListName = null,
        ?string $tierDescription = null,
        ?string $promotionName = null
    ): PriceResultData {
        $discountAmount = max(0, $originalPrice - $finalPrice);
        $discountPercentage = $originalPrice > 0
            ? round(($discountAmount / $originalPrice) * 100, 1)
            : null;

        return new PriceResultData(
            originalPrice: $originalPrice,
            finalPrice: $finalPrice,
            discountAmount: $discountAmount,
            discountSource: $discountSource,
            discountPercentage: $discountPercentage,
            priceListName: $priceListName,
            tierDescription: $tierDescription,
            promotionName: $promotionName,
            breakdown: $breakdown,
        );
    }
}
