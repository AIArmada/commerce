<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\Pricing\Contracts\SegmentPriceResolverInterface;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Support\Arr;

final class SegmentPriceResolver implements SegmentPriceResolverInterface
{
    use ResolvesCurrency;
    use ResolvesEffectiveAt;

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $priceableType, string $priceableId, int $quantity, array $context): ?int
    {
        $segmentIds = Arr::get($context, 'segment_ids');

        if (! is_array($segmentIds) || $segmentIds === []) {
            return null;
        }

        $effectiveAt = $this->resolveEffectiveAt($context);
        $currency = $this->resolveCurrency($context);

        $price = Price::query()
            ->where('priceable_type', $priceableType)
            ->where('priceable_id', $priceableId)
            ->where('currency', $currency)
            ->active($effectiveAt)
            ->forQuantity($quantity)
            ->whereIn(
                'price_list_id',
                PriceList::query()
                    ->active($effectiveAt)
                    ->where('currency', $currency)
                    ->whereIn('segment_id', $segmentIds)
                    ->select('id')
            )
            ->orderBy('amount', 'asc')
            ->orderByDesc('min_quantity')
            ->orderBy('id')
            ->value('amount');

        return $price === null ? null : (int) $price;
    }
}
