<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\Pricing\Contracts\CustomerPriceResolverInterface;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Throwable;

final class CustomerPriceResolver implements CustomerPriceResolverInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $priceableType, string $priceableId, int $quantity, array $context): ?int
    {
        $customerId = Arr::get($context, 'customer_id');

        if (! is_string($customerId) || $customerId === '') {
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
                    ->where('customer_id', $customerId)
                    ->select('id')
            )
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
