<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Services;

use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Pricing\Models\Promotion;

class PriceCalculator
{
    /**
     * Calculate the final price for a priceable item.
     *
     * @param  array<string, mixed>  $context
     */
    public function calculate(Priceable $item, int $quantity = 1, array $context = []): PriceResultData
    {
        $basePrice = $item->getBasePrice();
        $breakdown = [];

        // 1. Check for customer-specific price
        $customerPrice = $this->getCustomerPrice($item, $context);
        if ($customerPrice !== null) {
            $breakdown[] = ['type' => 'customer_specific', 'price' => $customerPrice];

            return $this->buildResult($basePrice, $customerPrice, 'Customer Specific Price', $breakdown);
        }

        // 2. Check for segment price
        $segmentPrice = $this->getSegmentPrice($item, $context);
        if ($segmentPrice !== null) {
            $breakdown[] = ['type' => 'segment', 'price' => $segmentPrice];

            return $this->buildResult($basePrice, $segmentPrice, 'Segment Price', $breakdown);
        }

        // 3. Check for tier pricing
        $tierResult = $this->getTierPrice($item, $quantity);
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
        $promotionResult = $this->getPromotionPrice($item, $basePrice);
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
        $priceListResult = $this->getPriceListPrice($item, $context);
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
     * Get customer-specific price.
     *
     * @param  array<string, mixed>  $context
     */
    protected function getCustomerPrice(Priceable $item, array $context): ?int
    {
        if (! isset($context['customer_id'])) {
            return null;
        }

        // Look up customer-specific pricing
        $price = Price::query()
            ->where('priceable_type', get_class($item))
            ->where('priceable_id', $item->getBuyableIdentifier())
            ->whereHas('priceList', function ($query) use ($context): void {
                $query->where('customer_id', $context['customer_id']);
            })
            ->active()
            ->first();

        return $price?->amount;
    }

    /**
     * Get segment-based price.
     *
     * @param  array<string, mixed>  $context
     */
    protected function getSegmentPrice(Priceable $item, array $context): ?int
    {
        if (! isset($context['segment_ids'])) {
            return null;
        }

        $price = Price::query()
            ->where('priceable_type', get_class($item))
            ->where('priceable_id', $item->getBuyableIdentifier())
            ->whereHas('priceList', function ($query) use ($context): void {
                $query->whereIn('segment_id', $context['segment_ids']);
            })
            ->active()
            ->orderBy('amount', 'asc') // Best price
            ->first();

        return $price?->amount;
    }

    /**
     * Get tier-based price for quantity.
     *
     * @return array{price: int, tier: string}|null
     */
    protected function getTierPrice(Priceable $item, int $quantity): ?array
    {
        if ($quantity <= 1) {
            return null;
        }

        $tier = PriceTier::query()
            ->where('tierable_type', get_class($item))
            ->where('tierable_id', $item->getBuyableIdentifier())
            ->forQuantity($quantity)
            ->orderBy('min_quantity', 'desc')
            ->first();

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
    protected function getPromotionPrice(Priceable $item, int $basePrice): ?array
    {
        $promotion = Promotion::query()
            ->active()
            ->whereHas('products', function ($query) use ($item): void {
                $query->where('promotionable_id', $item->getBuyableIdentifier());
            })
            ->orderBy('priority', 'desc')
            ->first();

        if (! $promotion) {
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
    protected function getPriceListPrice(Priceable $item, array $context): ?array
    {
        $priceListId = $context['price_list_id'] ?? null;

        $query = Price::query()
            ->where('priceable_type', get_class($item))
            ->where('priceable_id', $item->getBuyableIdentifier())
            ->active();

        if ($priceListId) {
            $query->where('price_list_id', $priceListId);
        } else {
            $query->whereHas('priceList', fn ($q) => $q->active()->default());
        }

        $price = $query->first();

        if (! $price) {
            return null;
        }

        return [
            'price' => $price->amount,
            'name' => $price->priceList?->name ?? 'Default',
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
