<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\Pricing\Contracts\SegmentPriceResolverInterface;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Support\Arr;

final class SegmentPriceResolver implements SegmentPriceResolverInterface
{
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

        $price = Price::query()
            ->where('priceable_type', $priceableType)
            ->where('priceable_id', $priceableId)
            ->whereNull('deactivated_at')
            ->forQuantity($quantity)
            ->where(function ($q) use ($effectiveAt): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $effectiveAt);
            })
            ->where(function ($q) use ($effectiveAt): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $effectiveAt);
            })
            ->whereIn(
                'price_list_id',
                PriceList::query()
                    ->where('is_active', true)
                    ->whereNull('deactivated_at')
                    ->where(function ($q) use ($effectiveAt): void {
                        $q->whereNull('starts_at')->orWhere('starts_at', '<=', $effectiveAt);
                    })
                    ->where(function ($q) use ($effectiveAt): void {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>=', $effectiveAt);
                    })
                    ->whereIn('segment_id', $segmentIds)
                    ->select('id')
            )
            ->orderBy('amount', 'asc')
            ->orderByDesc('min_quantity')
            ->first();

        return $price?->amount;
    }
}
