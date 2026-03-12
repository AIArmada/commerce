<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources;

use AIArmada\FilamentCashierChip\Support\CashierChipOwnerScope;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

abstract class BaseCashierChipResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    abstract protected static function navigationSortKey(): string;

    final public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-cashier-chip.navigation.group');
    }

    final public static function getNavigationSort(): ?int
    {
        return config('filament-cashier-chip.resources.navigation_sort.' . static::navigationSortKey());
    }

    final public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    final public static function getNavigationBadgeColor(): ?string
    {
        return config('filament-cashier-chip.navigation.badge_color', 'success');
    }

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return CashierChipOwnerScope::apply(parent::getEloquentQuery());
    }

    protected static function pollingInterval(): string
    {
        return (string) config('filament-cashier-chip.tables.polling_interval', '45s');
    }

    protected static function formatAmount(int $amount, ?string $currency = null): string
    {
        $currency = $currency ?? config('cashier-chip.currency', 'MYR');
        $precision = (int) config('filament-cashier-chip.tables.amount_precision', 2);
        $value = $amount / 100;

        return mb_strtoupper($currency) . ' ' . number_format($value, $precision, '.', ',');
    }
}
