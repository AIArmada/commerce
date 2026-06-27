<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources;

use AIArmada\CashierChip\Billing\Cashier as CashierChip;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Tables\SubscriptionsTable;
use BackedEnum;
use Closure;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Laravel\Cashier\Subscription;

final class UnifiedSubscriptionResource extends Resource
{
    public static function getModel(): string
    {
        if (class_exists(Subscription::class)) {
            return Subscription::class;
        }

        if (class_exists(CashierChip::class)) {
            return CashierChip::$subscriptionModel;
        }

        $userModel = config('auth.providers.users.model');

        if (is_string($userModel)) {
            return $userModel;
        }

        return User::class;
    }

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCreditCard;

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-cashier.resources.navigation_sort.subscriptions', 10);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-cashier.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::subscriptions.title');
    }

    public static function getModelLabel(): string
    {
        return __('filament-cashier::subscriptions.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cashier::subscriptions.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function table(Table $table): Table
    {
        return SubscriptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
            'create' => Pages\CreateSubscription::route('/create'),
        ];
    }

    /**
     * Disable Eloquent binding - we use DTOs.
     */
    public static function resolveRecordRouteBinding(int | string $key, ?Closure $modifyQuery = null): ?Model
    {
        return null;
    }
}
