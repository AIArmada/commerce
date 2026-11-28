<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Services;

use AIArmada\CommerceSupport\Contracts\Priceable;
use AIArmada\CommerceSupport\Support\MoneyNormalizer;
use AIArmada\Pricing\Contracts\CustomerPriceResolverInterface;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\Contracts\SegmentPriceResolverInterface;
use AIArmada\Pricing\Contracts\TierResolverInterface;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Pricing\Events\PriceCalculated;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Queries\EligiblePriceLists;
use AIArmada\Pricing\Support\PromotionalPriceResolver;
use AIArmada\Pricing\Support\ResolvesCurrency;
use AIArmada\Pricing\Support\ResolvesEffectiveAt;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

final class PriceCalculator implements PriceCalculatorInterface
{
    use ResolvesCurrency;
    use ResolvesEffectiveAt;

    public function __construct(
        private readonly TierResolverInterface $tierResolver,
        private readonly PromotionalPriceResolver $promotionalResolver,
        private readonly CustomerPriceResolverInterface $customerPriceResolver,
        private readonly SegmentPriceResolverInterface $segmentPriceResolver,
    ) {}

    /**
     * Calculate prices for many lines at once, resolving the requested or
     * default price list a single time instead of once per line.
     *
     * @param  array<int, array{item: Priceable, quantity?: int}>  $lines
     * @param  array<string, mixed>  $context
     * @return array<int, PriceResultData>
     */
    public function calculateMany(array $lines, array $context = []): array
    {
        if ($lines === []) {
            return [];
        }

        $context['effective_at'] = $this->resolveEffectiveAt($context);
        $priceListId = Arr::get($context, 'price_list_id');
        $priceList = is_string($priceListId) && $priceListId !== ''
            ? EligiblePriceLists::query($context, $context['effective_at'], $this->resolveCurrency($context))->whereKey($priceListId)->first()
            : $this->resolveDefaultPriceList($context);

        $results = [];

        foreach ($lines as $index => $line) {
            $results[$index] = $this->calculateWithList($line['item'], $line['quantity'] ?? 1, $context, $priceList, true);
        }

        return $results;
    }

    public function calculate(Priceable $item, int $quantity = 1, array $context = []): PriceResultData
    {
        return $this->calculateWithList($item, $quantity, $context, null, false);
    }

    private function calculateWithList(Priceable $item, int $quantity, array $context, ?PriceList $resolvedList, bool $listResolved): PriceResultData
    {
        $effectiveAt = $this->resolveEffectiveAt($context);
        $context['effective_at'] = $effectiveAt;

        $quantity = max(1, $quantity);
        $basePrice = MoneyNormalizer::toCents($item->getBasePrice());
        $breakdown = [];

        $currency = $this->resolveCurrency($context);
        $context['currency'] = $currency;

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

        if (! $listResolved) {
            $requestedListId = Arr::get($context, 'price_list_id');
            $resolvedList = is_string($requestedListId) && $requestedListId !== ''
                ? EligiblePriceLists::query($context, $effectiveAt, $currency)->whereKey($requestedListId)->first()
                : $this->resolveDefaultPriceList($context);
            $listResolved = true;
        }

        $tierContext = $context;
        $tierContext['price_list_id'] = $resolvedList?->id;

        $tierResult = $this->tierResolver->resolve($priceableType, $priceableId, $quantity, $tierContext);
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

        $promotionResult = $this->promotionalResolver->resolve(
            $priceableType,
            $priceableId,
            $basePrice,
            $quantity,
            $effectiveAt,
            $context,
        );
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

        $priceListResult = $this->getPriceListPrice($priceableType, $priceableId, $quantity, $currency, $effectiveAt, $resolvedList);
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

    private function getPriceListPrice(string $priceableType, string $priceableId, int $quantity, string $currency, CarbonImmutable $effectiveAt, ?PriceList $priceList): ?array
    {
        if (! $priceList) {
            return null;
        }

        $price = Price::query()->active($effectiveAt)
            ->where('price_list_id', $priceList->id)
            ->where('priceable_type', $priceableType)
            ->where('priceable_id', $priceableId)
            ->where('currency', $currency)
            ->forQuantity($quantity)
            ->orderByDesc('min_quantity')
            ->orderBy('id')
            ->value('amount');

        if ($price === null) {
            return null;
        }

        return [
            'price' => (int) $price,
            'name' => $priceList->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolveDefaultPriceList(array $context): ?PriceList
    {
        $effectiveAt = $this->resolveEffectiveAt($context);

        return EligiblePriceLists::query($context, $effectiveAt, $this->resolveCurrency($context))
            ->default()
            ->orderByDesc('priority')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
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
