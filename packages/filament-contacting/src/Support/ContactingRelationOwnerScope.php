<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ContactingRelationOwnerScope
{
    public static function scopeForParent(Builder $query, Model $parent): Builder
    {
        if (! method_exists($parent, 'ownerScopeConfig')) {
            return $query;
        }

        return OwnerUiScope::applyForRecordOwner($query, $parent);
    }

    public static function canAccessParent(Model $parent): bool
    {
        if (! method_exists($parent, 'ownerScopeConfig')) {
            return true;
        }

        return OwnerUiScope::canAccessRecord($parent);
    }
}
