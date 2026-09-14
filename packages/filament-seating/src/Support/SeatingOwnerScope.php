<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Unique;

final class SeatingOwnerScope
{
    public static function scopeUniqueRuleToOwner(Unique $rule): Unique
    {
        if (! (bool) config('seating.owner.enabled', true)) {
            return $rule;
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('seating.owner.include_global', false);

        if ($owner instanceof Model) {
            if ($includeGlobal) {
                return $rule->where(function (Builder $query) use ($owner): void {
                    $query
                        ->where(function (Builder $ownerQuery) use ($owner): void {
                            $ownerQuery
                                ->where('owner_type', $owner->getMorphClass())
                                ->where('owner_id', (string) $owner->getKey());
                        })
                        ->orWhere(function (Builder $globalQuery): void {
                            $globalQuery->whereNull('owner_type')->whereNull('owner_id');
                        });
                });
            }

            return $rule
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getKey());
        }

        return $rule
            ->whereNull('owner_type')
            ->whereNull('owner_id');
    }
}
