<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\Pricing\Contracts\SegmentPriceResolverInterface;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Queries\EligiblePriceLists;
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

        $segmentIds = is_array($segmentIds)
            ? array_values(array_filter($segmentIds, static fn (mixed $id): bool => is_string($id) && $id !== ''))
            : [];

        if ($segmentIds === []) {
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
                EligiblePriceLists::query($context, $effectiveAt, $currency)
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
