<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OwnerScopedQueries
{
    public static function owner(): ?Model
    {
        if (! self::isEnabled()) {
            return null;
        }

        return OwnerContext::resolve();
    }

    public static function isEnabled(): bool
    {
        return (bool) config('vouchers.owner.enabled', false);
    }

    public static function includeGlobal(): bool
    {
        return (bool) config('vouchers.owner.include_global', false);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeVoucherLike(Builder $query): Builder
    {
        if (! self::isEnabled()) {
            return $query;
        }

        $owner = self::owner();
        $includeGlobal = self::includeGlobal();

        return self::scopeOwnerColumns($query, $owner, $includeGlobal);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeOwnerColumns(Builder $query, ?Model $owner, bool $includeGlobal): Builder
    {
        if (! self::isEnabled()) {
            return $query;
        }

        return OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);
    }

    /**
     * @return Builder<Voucher>
     */
    public static function vouchers(): Builder
    {
        /** @var Builder<Voucher> $query */
        $query = Voucher::query();

        /** @var Builder<Voucher> $scoped */
        $scoped = self::scopeVoucherLike($query);

        return $scoped;
    }

    /**
     * @return Builder<Voucher>
     */
    public static function voucherIds(): Builder
    {
        return self::vouchers()->select('id');
    }

    /**
     * @return Builder<Voucher>
     */
    public static function voucherCodes(): Builder
    {
        return self::vouchers()->select('code');
    }
}
