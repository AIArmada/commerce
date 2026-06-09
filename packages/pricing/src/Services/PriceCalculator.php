<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Services;

use AIArmada\CommerceSupport\Support\MoneyNormalizer;
use AIArmada\Pricing\Contracts\CustomerPriceResolverInterface;
use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\Contracts\SegmentPriceResolverInterface;
use AIArmada\Pricing\Contracts\TierResolverInterface;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Pricing\Events\PriceCalculated;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Support\PricingOwnerScope;
use AIArmada\Pricing\Support\PromotionalPriceResolver;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Throwable;

final class PriceCalculator implements PriceCalculatorInterface
{
    public function __construct(
        private readonly TierResolverInterface $tierResolver,
        private readonly PromotionalPriceResolver $promotionalResolver,
        private readonly CustomerPriceResolverInterface $customerPriceResolver,
        private readonly SegmentPriceResolverInterface $segmentPriceResolver,
    ) {}

    protected function resolveEffectiveAt(array $context): CarbonImmutable
    {
        $effectiveAt = Arr::get($context, 'effective_at');

        if ($effectiveAt instanceof DateTimeInterface) {
            return CarbonImmutable::instance($effectiveAt);
        }

        if (is_int($effectiveAt)) {
            return CarbonImmutable::createFromTimestamp($effectiveAt);
        }

        if (is_string($effectiveAt) && $effectiveAt !== '') {
            try {
                return CarbonImmutable::parse($effectiveAt);
            } catch (Throwable) {
            }
        }

        return CarbonImmutable::now();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyPriceListActiveAt(Builder $query, CarbonImmutable $at): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($at): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($q) use ($at): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyPriceActiveAt(Builder $query, CarbonImmutable $at): Builder
    {
        return $query
            ->where(function ($q) use ($at): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($q) use ($at): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }

    public function calculate(Priceable $item, int $quantity = 1, array $context = []): PriceResultData
    {
        $effectiveAt = $this->resolveEffectiveAt($context);

        $quantity = max(1, $quantity);
        $basePrice = MoneyNormalizer::toCents($item->getBasePrice());
        $breakdown = [];

        $currency = Arr::get($context, 'currency');
        $currency = is_string($currency) && $currency !== ''
            ? $currency
            : (string) config('pricing.defaults.currency', 'MYR');

        $priceableType = $this->getPriceableMorphType($item);
        $priceableId = $item->getBuyableIdentifier();

        $customerPrice = $this->customerPriceResolver->resolve($priceableType, $priceableId, $quantity, $context);
        if ($customerPrice !== null) {
            $breakdown[] = ['type' => 'customer_specific', 'price' => $customerPrice];

            $result = $this->buildResult($basePrice, $customerPrice, 'Customer Specific Price', $breakdown, currency: $currency);
            PriceCalculated::dispatch($item, $result, $quantity, $context);

            return $result;
        }

        $segmentPrice = $this->segmentPriceResolver->resolve($priceableType, $priceableId, $quantity, $context);
        if ($segmentPrice !== null) {
            $breakdown[] = ['type' => 'segment', 'price' => $segmentPrice];

            $result = $this->buildResult($basePrice, $segmentPrice, 'Segment Price', $breakdown, currency: $currency);
            PriceCalculated::dispatch($item, $result, $quantity, $context);

            return $result;
        }

        $tierResult = $this->tierResolver->resolve($priceableType, $priceableId, $quantity, $context);
        if ($tierResult !== null) {
            $breakdown[] = ['type' => 'tier', 'price' => $tierResult->price, 'tier' => $tierResult->tier];

            $result = $this->buildResult(
                $basePrice,
                $tierResult->price,
                'Tier Pricing',
                $breakdown,
                tierDescription: $tierResult->tier,
                currency: $currency
            );
            PriceCalculated::dispatch($item, $result, $quantity, $context);

            return $result;
        }

        $promotionResult = $this->promotionalResolver->resolve($priceableType, $priceableId, $basePrice, $quantity, $effectiveAt);
        if ($promotionResult !== null) {
            $breakdown[] = ['type' => 'promotion', 'price' => $promotionResult['price'], 'promotion' => $promotionResult['name']];

            $result = $this->buildResult(
                $basePrice,
                $promotionResult['price'],
                'Promotion',
                $breakdown,
                promotionName: $promotionResult['name'],
                currency: $currency
            );
            PriceCalculated::dispatch($item, $result, $quantity, $context);

            return $result;
        }

        $priceListResult = $this->getPriceListPrice($priceableType, $priceableId, $quantity, $context, $effectiveAt);
        if ($priceListResult !== null) {
            $breakdown[] = ['type' => 'price_list', 'price' => $priceListResult['price'], 'list' => $priceListResult['name']];

            $result = $this->buildResult(
                $basePrice,
                $priceListResult['price'],
                'Price List',
                $breakdown,
                priceListName: $priceListResult['name'],
                currency: $currency
            );
            PriceCalculated::dispatch($item, $result, $quantity, $context);

            return $result;
        }

        $breakdown[] = ['type' => 'base', 'price' => $basePrice];

        $result = $this->buildResult($basePrice, $basePrice, null, $breakdown, currency: $currency);
        PriceCalculated::dispatch($item, $result, $quantity, $context);

        return $result;
    }

    protected function getPriceableMorphType(Priceable $item): string
    {
        if ($item instanceof Model) {
            return $item->getMorphClass();
        }

        return get_class($item);
    }

    protected function getPriceListPrice(string $priceableType, string $priceableId, int $quantity, array $context, CarbonImmutable $effectiveAt): ?array
    {
        $priceListId = Arr::get($context, 'price_list_id');

        $priceListQuery = $this->applyPriceListActiveAt(PricingOwnerScope::applyToOwnedQuery(PriceList::query()), $effectiveAt);

        $priceList = is_string($priceListId) && $priceListId !== ''
            ? $priceListQuery->whereKey($priceListId)->first()
            : $priceListQuery->default()->orderByDesc('priority')->first();

        if (! $priceList) {
            return null;
        }

        $price = $this->applyPriceActiveAt(PricingOwnerScope::applyToOwnedQuery(Price::query()), $effectiveAt)
            ->where('price_list_id', $priceList->id)
            ->where('priceable_type', $priceableType)
            ->where('priceable_id', $priceableId)
            ->forQuantity($quantity)
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

    protected function buildResult(
        int $originalPrice,
        int $finalPrice,
        ?string $discountSource,
        array $breakdown,
        ?string $priceListName = null,
        ?string $tierDescription = null,
        ?string $promotionName = null,
        string $currency = 'MYR'
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
            currency: $currency,
            breakdown: $breakdown,
        );
    }
}
