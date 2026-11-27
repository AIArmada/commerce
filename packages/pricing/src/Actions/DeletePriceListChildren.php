<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class DeletePriceListChildren
{
    public function execute(PriceList $list): void
    {
        if (config('pricing.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);

            if (($owner === null && ! $list->isGlobal()) || ($owner !== null && ! $list->belongsToOwner($owner))) {
                throw new AuthorizationException('Cannot delete prices outside the current owner scope.');
            }
        }

        $list->prices()->delete();
        $list->tiers()->delete();
    }
}
