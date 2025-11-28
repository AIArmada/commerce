<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources\StockTransactionResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class StockTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transaction Overview')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('id')
                                ->label('Transaction ID')
                                ->copyable()
                                ->badge(),

                            TextEntry::make('type')
                                ->label('Type')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'in' => 'success',
                                    'out' => 'danger',
                                    default => 'gray',
                                })
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'in' => 'Inbound',
                                    'out' => 'Outbound',
                                    default => $state,
                                }),

                            TextEntry::make('quantity')
                                ->label('Quantity')
                                ->badge()
                                ->color('primary'),
                        ]),

                    Grid::make(3)
                        ->schema([
                            TextEntry::make('reason')
                                ->label('Reason')
                                ->badge()
                                ->color(fn (?string $state): string => match ($state) {
                                    'sale' => 'success',
                                    'restock' => 'info',
                                    'adjustment' => 'warning',
                                    'return' => 'primary',
                                    'damaged' => 'danger',
                                    'expired' => 'danger',
                                    default => 'gray',
                                })
                                ->formatStateUsing(fn (?string $state): string => $state !== null
                                    ? ucfirst($state)
                                    : '-'
                                ),

                            TextEntry::make('transaction_date')
                                ->label('Transaction Date')
                                ->dateTime(),

                            TextEntry::make('user.name')
                                ->label('Performed By')
                                ->default('System'),
                        ]),
                ]),

            Section::make('Stockable Item')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('stockable_type')
                                ->label('Item Type')
                                ->formatStateUsing(fn (string $state): string => class_basename($state)),

                            TextEntry::make('stockable_id')
                                ->label('Item ID')
                                ->copyable(),
                        ]),
                ]),

            Section::make('Notes')
                ->schema([
                    TextEntry::make('note')
                        ->label('Note')
                        ->default('-')
                        ->columnSpanFull(),
                ]),

            Section::make('Timestamps')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),

                            TextEntry::make('updated_at')
                                ->label('Updated')
                                ->dateTime(),
                        ]),
                ])
                ->collapsed(),
        ]);
    }
}
