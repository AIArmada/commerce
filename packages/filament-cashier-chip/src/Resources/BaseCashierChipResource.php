<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources;

use AIArmada\CashierChip\Contracts\CustomerOwnerResolverInterface;
use AIArmada\CashierChip\Support\DefaultCustomerOwnerResolver;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerCache;
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
        try {
            $count = OwnerCache::remember(
                OwnerContext::resolve(),
                'filament-cashier-chip.badge.' . static::navigationSortKey(),
                (int) config('filament-cashier-chip.navigation.badge_cache_ttl', 30),
                fn (): int => static::getEloquentQuery()->count(),
            );
        } catch (NoCurrentOwnerException) {
            return null;
        }

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

        if (! (bool) config('cashier-chip.features.owner.enabled', false)) {
            return $query;
        }

        $model = $query->getModel();

        if ($model === null) {
            return $query->whereRaw('1 = 0');
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            sprintf('%s requires an owner context or explicit global context.', static::class),
        );

        if (! method_exists($model, 'ownerScopeConfig')) {
            return static::customerOwnerResolver()->scopeQuery($query, $owner);
        }

        $config = $model->ownerScopeConfig();

        $query->withoutGlobalScope(OwnerScope::class);

        return OwnerQuery::applyToEloquentBuilder(
            $query,
            $owner,
            false,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }

    public static function customerOwnerResolver(): CustomerOwnerResolverInterface
    {
        $configured = config('cashier-chip.features.owner.customer_resolver');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            $resolved = app($configured);

            if ($resolved instanceof CustomerOwnerResolverInterface) {
                return $resolved;
            }
        }

        return app(DefaultCustomerOwnerResolver::class);
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
