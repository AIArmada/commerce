<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
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
        $query = parent::getEloquentQuery();

        if (! (bool) config('cashier-chip.features.owner.enabled', true)) {
            return $query;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            sprintf('%s requires an owner context or explicit global context.', static::class),
        );

        $model = $query->getModel();

        if ($model === null) {
            return $query->whereRaw('1 = 0');
        }

        $ownerTypeColumn = 'owner_type';
        $ownerIdColumn = 'owner_id';

        if (method_exists($model, 'ownerScopeConfig')) {
            $config = $model->ownerScopeConfig();
            $ownerTypeColumn = $config->ownerTypeColumn;
            $ownerIdColumn = $config->ownerIdColumn;
        }

        $query->withoutGlobalScope(OwnerScope::class);

        return OwnerQuery::applyToEloquentBuilder(
            $query,
            $owner,
            false,
            $ownerTypeColumn,
            $ownerIdColumn,
        );
    }

    protected static function pollingInterval(): string
    {
        return (string) config('filament-cashier-chip.tables.polling_interval', '45s');
    }

    protected static function formatAmount(int $amount, ?string $currency = null): string
    {
        $currency = $currency ?? config('cashier-chip.currency', 'MYR');

        return MoneyFormatter::formatMinorWithCode($amount, $currency);
    }
}
