<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Collection;
use Illuminate\Support\Facades\DB;

final readonly class RebuildCollectionMembership
{
    public function execute(Collection $collection): void
    {
        DB::transaction(function () use ($collection): void {
            $query = Collection::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $locked = $query->whereKey($collection->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isAutomatic()) {
                return;
            }

            $locked->matchingProductsQuery()->select('id')->chunkById(500, function ($products) use ($locked): void {
                $locked->products()->syncWithoutDetaching($products->pluck('id')->all());
            });

            $locked->products()->newPivotQuery()->select('product_id')->chunkById(500, function ($rows) use ($locked): void {
                $pivotIds = $rows->pluck('product_id')->all();
                $stillMatching = $locked->matchingProductsQuery()->whereIn('id', $pivotIds)->pluck('id')->all();
                $stale = array_values(array_diff($pivotIds, $stillMatching));

                if ($stale !== []) {
                    $locked->products()->detach($stale);
                }
            }, 'product_id');
        }, 3);
    }
}
