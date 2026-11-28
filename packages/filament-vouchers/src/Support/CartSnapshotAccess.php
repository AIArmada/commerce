<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support;

use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CartSnapshotAccess
{
    private static function enabled(): bool
    {
        return (bool) config('cart.owner.enabled', false) || (bool) config('vouchers.owner.enabled', false);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query): Builder
    {
        if (! self::enabled()) {
            return $query;
        }

        $config = CartSnapshot::ownerScopingEnabled()
            ? CartSnapshot::ownerScopeConfig()
            : Voucher::ownerScopeConfig();
        $owner = OwnerContext::resolveConfigured($config);
        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        return OwnerQuery::applyToEloquentBuilder($query, $owner, false);
    }

    public static function allows(Model $record): bool
    {
        if (! $record instanceof CartSnapshot) {
            return false;
        }

        if (! self::enabled()) {
            return true;
        }

        try {
            return self::scope(CartSnapshot::query())->whereKey($record->getKey())->exists();
        } catch (NoCurrentOwnerException) {
            return false;
        }
    }
}
