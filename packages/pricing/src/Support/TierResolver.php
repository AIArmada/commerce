<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\Pricing\Contracts\TierResolverInterface;
use AIArmada\Pricing\Data\TierPriceResultData;
use AIArmada\Pricing\Events\TierApplied;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Pricing\Queries\EligiblePriceLists;
use Illuminate\Support\Arr;

final class TierResolver implements TierResolverInterface
{
    use ResolvesCurrency;
    use ResolvesEffectiveAt;

    public function resolve(string $tierableType, string $tierableId, int $quantity, array $context): ?TierPriceResultData
    {
        if ($quantity <= 1) {
            return null;
        }

        $priceListId = Arr::get($context, 'price_list_id');
        $currency = $this->resolveCurrency($context);

        $query = PriceTier::query()
            ->where('tierable_type', $tierableType)
            ->where('tierable_id', $tierableId)
            ->where('is_active', true)
            ->where('currency', $currency)
            ->forQuantity($quantity)
            ->when(is_string($priceListId) && $priceListId !== '', function ($q) use ($priceListId, $context, $currency): void {
                $eligibleList = EligiblePriceLists::query($context, $this->resolveEffectiveAt($context), $currency)
                    ->whereKey($priceListId)
                    ->select('id');

                $q->where(function ($inner) use ($eligibleList): void {
                    $inner->whereIn('price_list_id', $eligibleList)->orWhereNull('price_list_id');
                })->orderByRaw('CASE WHEN price_list_id IS NULL THEN 1 ELSE 0 END');
            }, function ($q): void {
                $q->whereNull('price_list_id');
            });

        $tier = $query->orderByDesc('min_quantity')->orderBy('id')->first();

        if (! $tier) {
            return null;
        }

        $result = new TierPriceResultData(
            price: $tier->amount,
            tier: $tier->getDescription(),
        );

        TierApplied::dispatch($tierableType, $tierableId, $quantity, $result, $context);

        return $result;
    }
}
