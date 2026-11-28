<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Models\TaxRate;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CascadeTaxZoneDeletionAction
{
    public function execute(TaxZone $zone): void
    {
        if (config('tax.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();

            if ($owner === null) {
                if ($zone->owner_type !== null || $zone->owner_id !== null) {
                    throw new AuthorizationException('Cannot delete owned tax zones without an owner context.');
                }
            } elseif (! $zone->belongsToOwner($owner)) {
                throw new AuthorizationException('Cannot delete tax zones outside the current owner scope.');
            }

            if ($zone->owner_type === null && $zone->owner_id === null) {
                $hasOwnedRates = TaxRate::query()
                    ->withoutOwnerScope()
                    ->where('zone_id', $zone->id)
                    ->whereNotNull('owner_type')
                    ->whereNotNull('owner_id')
                    ->exists();

                if ($hasOwnedRates) {
                    throw new AuthorizationException('Cannot delete a global tax zone while owned rates exist.');
                }

                TaxRate::query()
                    ->withoutOwnerScope()
                    ->where('zone_id', $zone->id)
                    ->whereNull('owner_type')
                    ->whereNull('owner_id')
                    ->delete();

                return;
            }

            TaxRate::query()
                ->withoutOwnerScope()
                ->where('zone_id', $zone->id)
                ->where('owner_type', $zone->owner_type)
                ->where('owner_id', $zone->owner_id)
                ->delete();

            return;
        }

        $zone->rates()->delete();
    }
}
