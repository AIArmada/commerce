<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class NavigationBadgeHelper
{
    public static function getNavigationBadge(string $resourceClass, Builder $query): ?string
    {
        if (Filament::auth()?->user() === null) {
            return null;
        }

        $owner = OwnerUiScope::resolveOwner($query);
        $ownerKey = $owner instanceof Model
            ? $owner->getMorphClass() . ':' . (string) $owner->getKey()
            : 'none';

        $includeGlobal = (bool) config('jnt.owner.include_global', false);
        $cacheKey = 'filament-jnt:nav-badge:' . $resourceClass . ':' . $ownerKey . ':' . ($includeGlobal ? '1' : '0');

        $count = Cache::remember($cacheKey, CarbonImmutable::now()->addSeconds(30), static fn (): int => $query->count());

        return $count > 0 ? (string) $count : null;
    }
}
