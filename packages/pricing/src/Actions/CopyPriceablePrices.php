<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Models\Price;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class CopyPriceablePrices
{
    public function execute(Model $source, Model $target): void
    {
        if (! $source->exists || ! $target->exists || $source->getMorphClass() !== $target->getMorphClass()) {
            throw new InvalidArgumentException('Price copying requires persisted records of the same type.');
        }

        DB::transaction(function () use ($source, $target): void {
            $query = Price::query();

            if (config('pricing.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $query->where('priceable_type', $source->getMorphClass())
                ->where('priceable_id', $source->getKey())
                ->orderBy('id')
                ->chunkById(100, function ($prices) use ($target): void {
                    foreach ($prices as $price) {
                        $copy = $price->replicate();
                        $copy->priceable_id = $target->getKey();
                        $copy->save();
                    }
                });
        });
    }
}
