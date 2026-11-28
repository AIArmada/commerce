<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCatalogRecord
{
    public function execute(Attribute | AttributeGroup | AttributeSet | Category | Collection | Product $record): bool
    {
        return DB::transaction(function () use ($record): bool {
            $query = $record->newQuery();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $record->scopeForOwner($query, $owner, false);
            }

            if ($record instanceof Category) {
                (clone $query)->orderBy('id')->lockForUpdate()->pluck('id');
            }

            $locked = $query->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            return (bool) $locked->delete();
        }, 3);
    }
}
