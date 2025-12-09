<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\Textarea;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string | UnitEnum | null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Information')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('order_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'ORD-' . mb_strtoupper(uniqid())),

                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->required()
                            ->default('pending'),

                        Select::make('payment_status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                            ])
                            ->required()
                            ->default('pending'),
                    ])
                    ->columns(2),

                Section::make('Order Totals')
                    ->schema([
                        TextInput::make('subtotal')
                            ->numeric()
                            ->prefix('RM')
                            ->required()
                            ->default(0),

                        TextInput::make('tax')
                            ->numeric()
                            ->prefix('RM')
                            ->default(0),

                        TextInput::make('discount')
                            ->numeric()
                            ->prefix('RM')
                            ->default(0),

                        TextInput::make('shipping')
                            ->numeric()
                            ->prefix('RM')
                            ->default(0),

                        TextInput::make('total')
                            ->numeric()
                            ->prefix('RM')
                            ->required()
                            ->default(0),
                    ])
                    ->columns(5),

                Section::make('Shipping Address')
                    ->schema([
                        TextInput::make('shipping_address.name')
                            ->label('Name')
                            ->maxLength(255),

                        TextInput::make('shipping_address.phone')
                            ->label('Phone')
                            ->maxLength(50),

                        Textarea::make('shipping_address.address')
                            ->label('Address')
                            ->columnSpanFull(),

                        TextInput::make('shipping_address.city')
                            ->label('City'),

                        TextInput::make('shipping_address.state')
                            ->label('State'),

                        TextInput::make('shipping_address.postcode')
                            ->label('Postcode'),

                        TextInput::make('shipping_address.country')
                            ->label('Country')
                            ->default('Malaysia'),
                    ])
                    ->columns(2),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
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

                        TextEntry::make('user.name')
                            ->label('Customer'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string | array => match ($state) {
                                'pending' => 'warning',
                                'processing' => 'info',
                                'shipped' => 'primary',
                                'delivered' => 'success',
                                'cancelled' => 'danger',
                                'refunded' => 'gray',
                                default => 'gray',
                            }),

                        TextEntry::make('payment_status')
                            ->badge()
                            ->color(fn (string $state): string | array => match ($state) {
                                'pending' => 'warning',
                                'paid' => 'success',
                                'failed' => 'danger',
                                'refunded' => 'gray',
                                default => 'gray',
                            }),

                        TextEntry::make('created_at')
                            ->label('Ordered At')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Section::make('Order Totals')
                    ->schema([
                        TextEntry::make('subtotal')
                            ->formatStateUsing(fn (?int $state): ?string => $state !== null ? 'MYR ' . number_format($state / 100, 2) : null),

                        TextEntry::make('tax_total')
                            ->label('Tax')
                            ->formatStateUsing(fn (?int $state): ?string => $state !== null ? 'MYR ' . number_format($state / 100, 2) : null),

                        TextEntry::make('discount_total')
                            ->label('Discount')
                            ->formatStateUsing(fn (?int $state): ?string => $state !== null ? 'MYR ' . number_format($state / 100, 2) : null),

                        TextEntry::make('shipping_total')
                            ->label('Shipping')
                            ->formatStateUsing(fn (?int $state): ?string => $state !== null ? 'MYR ' . number_format($state / 100, 2) : null),

                        TextEntry::make('grand_total')
                            ->label('Total')
                            ->formatStateUsing(fn (?int $state): ?string => $state !== null ? 'MYR ' . number_format($state / 100, 2) : null)
                            ->size('lg'),
                    ])
                    ->columns(5),

                Section::make('Shipping Address')
                    ->schema([
                        TextEntry::make('shipping_address.name')
                            ->label('Name'),

                        TextEntry::make('shipping_address.phone')
                            ->label('Phone'),

                        TextEntry::make('shipping_address.address')
                            ->label('Address')
                            ->columnSpanFull(),

                        TextEntry::make('shipping_address.city')
                            ->label('City'),

                        TextEntry::make('shipping_address.state')
                            ->label('State'),

                        TextEntry::make('shipping_address.postcode')
                            ->label('Postcode'),

                        TextEntry::make('shipping_address.country')
                            ->label('Country'),
                    ])
                    ->columns(2),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->hidden(fn (Order $record): bool => empty($record->notes)),
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

                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(fn (?int $state): ?string => $state !== null ? 'MYR ' . number_format($state / 100, 2) : null)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Ordered')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ]),

                SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
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

    public static function getNavigationBadge(): ?string
    {
        return (string) self::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'warning';
    }
}
