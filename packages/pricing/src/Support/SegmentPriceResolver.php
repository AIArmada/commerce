<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\Pricing\Contracts\SegmentPriceResolverInterface;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Throwable;

final class SegmentPriceResolver implements SegmentPriceResolverInterface
{
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveEffectiveAt(array $context): CarbonImmutable
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
}
