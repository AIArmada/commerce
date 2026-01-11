<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string | UnitEnum | null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    /**
     * @return Builder<Order>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Order> $query */
        $query = parent::getEloquentQuery();

        $owner = OwnerContext::resolve();
        if ($owner === null) {
            return $query->whereRaw('1=0');
        }

        return $query->forOwner($owner);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('order_number')
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Details')
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('Order #')
                            ->copyable(),

                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => $state instanceof OrderStatus ? $state->label() : (string) $state)
                            ->color(fn (mixed $state): string | array => $state instanceof OrderStatus ? $state->color() : 'gray'),

                        TextEntry::make('paid_at')
                            ->label('Paid At')
                            ->dateTime(),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Section::make('Order Totals')
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->formatStateUsing(fn (mixed $state, Order $record): string => $record->getFormattedSubtotal()),

                        TextEntry::make('tax_total')
                            ->label('Tax')
                            ->formatStateUsing(fn (mixed $state, Order $record): string => $record->getFormattedTaxTotal()),

                        TextEntry::make('discount_total')
                            ->label('Discount')
                            ->formatStateUsing(fn (mixed $state, Order $record): string => $record->getFormattedDiscountTotal()),

                        TextEntry::make('shipping_total')
                            ->label('Shipping')
                            ->formatStateUsing(fn (mixed $state, Order $record): string => $record->getFormattedShippingTotal()),

                        TextEntry::make('grand_total')
                            ->label('Total')
                            ->formatStateUsing(fn (mixed $state, Order $record): string => $record->getFormattedGrandTotal())
                            ->size('lg'),
                    ])
                    ->columns(5),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof OrderStatus ? $state->label() : (string) $state)
                    ->color(fn (mixed $state): string | array => $state instanceof OrderStatus ? $state->color() : 'gray'),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(fn (mixed $state, Order $record): string => $record->getFormattedGrandTotal())
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('Paid')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $owner = OwnerContext::resolve();
        if ($owner === null) {
            return null;
        }

        $count = self::getModel()::query()
            ->forOwner($owner)
            ->whereNull('paid_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'warning';
    }
}
