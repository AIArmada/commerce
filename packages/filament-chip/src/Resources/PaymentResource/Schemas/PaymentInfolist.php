<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\PaymentResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Payment Summary')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('Payment #')
                                    ->copyable(),
                                TextEntry::make('payment_type')
                                    ->label('Type')
                                    ->badge(),
                                TextEntry::make('currency')
                                    ->label('Currency')
                                    ->badge(),
                                TextEntry::make('formatted_amount')
                                    ->label('Amount')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('formatted_net_amount')
                                    ->label('Net Amount')
                                    ->placeholder('—'),
                                TextEntry::make('formatted_fee_amount')
                                    ->label('Fees')
                                    ->color('warning')
                                    ->placeholder('—'),
                                IconEntry::make('is_outgoing')
                                    ->label('Outgoing')
                                    ->boolean()
                                    ->trueColor('info')
                                    ->falseColor('success'),
                            ]),
                    ]),
                Section::make('Timing')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_on')
                                    ->label('Created On')
                                    ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                    ->since(),
                                TextEntry::make('paid_on')
                                    ->label('Paid On')
                                    ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                    ->placeholder('—'),
                                TextEntry::make('remote_paid_on')
                                    ->label('Remote Paid On')
                                    ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                    ->placeholder('—'),
                                TextEntry::make('updated_on')
                                    ->label('Updated On')
                                    ->dateTime(config('filament-chip.tables.updated_on_format', 'Y-m-d H:i:s'))
                                    ->placeholder('—'),
                            ]),
                    ]),
                Fieldset::make('Purchase Reference')
                    ->inlineLabelled() // @phpstan-ignore method.notFound
                    ->schema([
                        TextEntry::make('purchase_id')
                            ->label('Purchase ID')
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('purchase.formatted_total')
                            ->label('Purchase Total')
                            ->placeholder('—'),
                        TextEntry::make('purchase.status')
                            ->label('Purchase Status')
                            ->badge()
                            ->placeholder('—'),
                    ]),
                Section::make('Additional Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('formatted_pending_amount')
                                    ->label('Pending Amount')
                                    ->placeholder('—'),
                                TextEntry::make('pending_unfreeze_on')
                                    ->label('Pending Unfreeze')
                                    ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                    ->placeholder('—'),
                                TextEntry::make('description')
                                    ->label('Description')
                                    ->columnSpanFull()
                                    ->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
