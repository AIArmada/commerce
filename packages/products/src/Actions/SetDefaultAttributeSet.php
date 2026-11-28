<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\AttributeSet;
use Illuminate\Support\Facades\DB;

final readonly class SetDefaultAttributeSet
{
    public function execute(AttributeSet $record): void
    {
        DB::transaction(function () use ($record): void {
            $query = AttributeSet::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            } else {
                $query->where('owner_type', $record->owner_type)->where('owner_id', $record->owner_id);
            }

            (clone $query)->orderBy('id')->lockForUpdate()->pluck('id');
            $locked = (clone $query)->whereKey($record->getKey())->firstOrFail();

            (clone $query)->where('id', '!=', $locked->getKey())->where('is_default', true)->update(['is_default' => false]);
            $locked->update(['is_default' => true]);
        }, 3);
    }
}
