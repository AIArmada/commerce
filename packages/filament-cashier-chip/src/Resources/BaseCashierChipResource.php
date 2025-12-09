<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources;

use Filament\Resources\Resource;
use UnitEnum;

abstract class BaseCashierChipResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    abstract protected static function navigationSortKey(): string;

    final public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-cashier-chip.navigation_group');
    }

    final public static function getNavigationSort(): ?int
    {
        return config('filament-cashier-chip.resources.navigation_sort.' . static::navigationSortKey());
    }

    final public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::count();

        return $count > 0 ? (string) $count : null;
    }

    final public static function getNavigationBadgeColor(): ?string
    {
        return config('filament-cashier-chip.navigation_badge_color', 'success');
    }

    protected static function pollingInterval(): string
    {
        return (string) config('filament-cashier-chip.polling_interval', '45s');
    }

    protected static function formatAmount(int $amount, ?string $currency = null): string
    {
        $currency = $currency ?? config('filament-cashier-chip.currency', 'MYR');
        $precision = (int) config('filament-cashier-chip.tables.amount_precision', 2);
        $value = $amount / 100;

        return mb_strtoupper($currency) . ' ' . number_format($value, $precision, '.', ',');
    }
}
