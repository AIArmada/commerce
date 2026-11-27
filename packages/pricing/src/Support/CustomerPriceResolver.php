<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\Pricing\Contracts\CustomerPriceResolverInterface;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Support\Arr;

final class CustomerPriceResolver implements CustomerPriceResolverInterface
{
    use ResolvesCurrency;
    use ResolvesEffectiveAt;

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
                    ->where('customer_id', $customerId)
                    ->select('id')
            )
            ->orderByDesc('min_quantity')
            ->orderBy('id')
            ->value('amount');

        return $price === null ? null : (int) $price;
    }
}
