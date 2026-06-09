<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources;

use AIArmada\FilamentOrders\Resources\OrderResource\Pages;
use AIArmada\FilamentOrders\Resources\OrderResource\RelationManagers;
use AIArmada\FilamentOrders\Resources\OrderResource\Schemas\OrderForm;
use AIArmada\FilamentOrders\Resources\OrderResource\Schemas\OrderInfolist;
use AIArmada\FilamentOrders\Resources\OrderResource\Tables\OrdersTable;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\Fraud;
use AIArmada\Orders\States\OnHold;
use AIArmada\Orders\States\PaymentFailed;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use AIArmada\Orders\States\Returned;
use AIArmada\Orders\States\Shipped;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('filament-orders.navigation.group', 'Sales');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-orders.navigation.sort', 1);
    }

    protected static ?string $recordTitleAttribute = 'order_number';

    /**
     * @return Builder<Order>
     */
    public static function getEloquentQuery(): Builder
    {
        $includeGlobal = (bool) config('orders.owner.include_global', false);

        return Order::query()
            ->forOwner(includeGlobal: $includeGlobal)
            ->with(['customer']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()->whereState('status', [PendingPayment::class, Processing::class])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Get all available order status options for forms/filters.
     *
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        $states = [
            Created::class,
            PendingPayment::class,
            Processing::class,
            OnHold::class,
            Fraud::class,
            Shipped::class,
            Delivered::class,
            Completed::class,
            Canceled::class,
            Returned::class,
            Refunded::class,
            PaymentFailed::class,
        ];

        $options = [];
        foreach ($states as $stateClass) {
            $instance = new $stateClass(new Order);
            $options[$stateClass::$name] = $instance->label();
        }

        return $options;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema(OrderForm::schema());
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema(OrderInfolist::schema());
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\RefundsRelationManager::class,
            RelationManagers\NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['order_number'];
    }
}
