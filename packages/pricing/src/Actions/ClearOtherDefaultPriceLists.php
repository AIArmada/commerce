<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ClearOtherDefaultPriceLists
{
    public function execute(PriceList $priceList): void
    {
        $query = PriceList::query();

        if (config('pricing.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);

            if (($owner === null && ! $priceList->isGlobal()) || ($owner !== null && ! $priceList->belongsToOwner($owner))) {
                throw new AuthorizationException('Cannot change default lists outside the current owner scope.');
            }

            $query = $query->forOwner($owner, false);
        }

        if ($priceList->exists) {
            $query->whereKeyNot($priceList->getKey());
        }

        $query->where('currency', $priceList->currency)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
