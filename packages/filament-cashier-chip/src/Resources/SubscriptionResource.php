<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources;

use AIArmada\CashierChip\Cashier;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Pages\ListSubscriptions;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Pages\ViewSubscription;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\RelationManagers\SubscriptionItemsRelationManager;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Schemas\SubscriptionInfolist;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Tables\SubscriptionTable;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

final class SubscriptionResource extends BaseCashierChipResource
{
    protected static ?string $model = null;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'type';

    public static function getModelLabel(): string
    {
        return __('filament-cashier-chip::filament-cashier-chip.subscription.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cashier-chip::filament-cashier-chip.subscription.plural');
    }

    public static function getModel(): string
    {
        return Cashier::$subscriptionModel;
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return SubscriptionTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return SubscriptionInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            SubscriptionItemsRelationManager::class,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'type',
            'chip_id',
            'chip_price',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'view' => ViewSubscription::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'subscriptions';
    }
}
