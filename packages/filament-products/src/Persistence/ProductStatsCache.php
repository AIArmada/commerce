<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Persistence;

use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Closure;
use Illuminate\Database\Eloquent\Model;

final class ProductStatsCache
{
    public const string KEY = 'filament-products.stats';

    /** @param Closure(): array<string, int|float> $load @return array<string, int|float> */
    public static function remember(?Model $owner, Closure $load): array
    {
        if ($owner !== null && config('products.features.owner.include_global', false)) {
            return $load();
        }

        return OwnerCache::remember($owner, self::KEY, 60, $load);
    }

    public static function forgetFor(Model $model): void
    {
        $owner = method_exists($model, 'getOwner')
            ? $model->getOwner()
            : OwnerContext::resolve();

        OwnerCache::forget($owner, self::KEY);
    }
}
