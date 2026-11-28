<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Queries;

use AIArmada\Pricing\Models\PriceList;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class EligiblePriceLists
{
    /**
     * @param  array<string, mixed>  $context
     * @return Builder<PriceList>
     */
    public static function query(array $context, CarbonImmutable $effectiveAt, string $currency): Builder
    {
        $customerId = $context['customer_id'] ?? null;
        $segmentIds = $context['segment_ids'] ?? [];

        $segmentIds = is_array($segmentIds)
            ? array_values(array_filter($segmentIds, static fn (mixed $id): bool => is_string($id) && $id !== ''))
            : [];

        return PriceList::query()
            ->active($effectiveAt)
            ->where('currency', $currency)
            ->where(function (Builder $query) use ($customerId): void {
                $query->whereNull('customer_id');

                if (is_string($customerId) && $customerId !== '') {
                    $query->orWhere('customer_id', $customerId);
                }
            })
            ->where(function (Builder $query) use ($segmentIds): void {
                $query->whereNull('segment_id');

                if ($segmentIds !== []) {
                    $query->orWhereIn('segment_id', $segmentIds);
                }
            });
    }
}
