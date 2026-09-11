<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Support;

use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

final class ProductStatsCache
{
    public const string KEY = 'filament-products.stats';

    public static function forgetFor(Model $model): void
    {
        $owner = method_exists($model, 'getOwner')
            ? $model->getOwner()
            : OwnerContext::resolve();

        OwnerCache::forget($owner, self::KEY);
    }
}
