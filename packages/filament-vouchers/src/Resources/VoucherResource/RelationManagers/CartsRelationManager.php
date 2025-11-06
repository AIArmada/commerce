<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers;

use AIArmada\FilamentCart\Models\Cart;
use Akaunting\Money\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CartsRelationManager extends RelationManager
{
    protected static string $relationship = 'appliedCarts';

    protected static ?string $recordTitleAttribute = 'identifier';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                TextEntry::make('identifier')
                    ->label('Cart Identifier')
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->copyable(),

                TextEntry::make('instance')
                    ->label('Instance')
                    ->badge()
                    ->color('info'),

                TextEntry::make('items_count')
                    ->label('Items')
                    ->numeric(),

                TextEntry::make('quantity')
                    ->label('Total Quantity')
                    ->numeric(),

                TextEntry::make('subtotal')
                    ->label('Subtotal')
                    ->formatStateUsing(fn (int $state, Cart $record): string => Money::{$record->currency}($state)->format()),

                TextEntry::make('total')
                    ->label('Total')
                    ->weight('bold')
                    ->formatStateUsing(fn (int $state, Cart $record): string => Money::{$record->currency}($state)->format()),

                TextEntry::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->since(),

                TextEntry::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->since(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Carts Using This Voucher')
            ->description('View all carts where this voucher is currently applied')
            ->columns([
                TextColumn::make('identifier')
                    ->label('Identifier')
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('instance')
                    ->label('Instance')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->formatStateUsing(fn (int $state, Cart $record): string => Money::{$record->currency}($state)->format())
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('total')
                    ->label('Total')
                    ->weight('bold')
                    ->formatStateUsing(fn (int $state, Cart $record): string => Money::{$record->currency}($state)->format())
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Carts Found')
            ->emptyStateDescription('This voucher has not been applied to any carts yet.')
            ->emptyStateIcon(Heroicon::OutlinedShoppingCart);
    }
}
