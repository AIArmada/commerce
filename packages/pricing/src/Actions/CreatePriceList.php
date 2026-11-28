<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Support\Facades\DB;

final readonly class CreatePriceList
{
    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes): PriceList
    {
        return DB::transaction(function () use ($attributes): PriceList {
            $record = new PriceList($attributes);

            if (config('pricing.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);

                if ($owner !== null) {
                    $record->assignOwner($owner);
                }
            }

            $record->save();

            return $record;
        });
    }
}
