<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class DocInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Document Information')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('doc_number')
                                    ->label('Document Number')
                                    ->copyable(),

                                TextEntry::make('doc_type')
                                    ->label('Type')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn ($state): string => $state->color()),

                                TextEntry::make('currency')
                                    ->label('Currency')
                                    ->badge(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('issue_date')
                                    ->label('Issue Date')
                                    ->date(),

                                TextEntry::make('due_date')
                                    ->label('Due Date')
                                    ->date()
                                    ->placeholder('No due date'),

                                TextEntry::make('paid_at')
                                    ->label('Paid At')
                                    ->dateTime()
                                    ->placeholder('Not paid'),
                            ]),
                    ]),

                Section::make('Customer')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('customer_data.name')
                                    ->label('Name'),

                                TextEntry::make('customer_data.email')
                                    ->label('Email')
                                    ->copyable(),

                                TextEntry::make('customer_data.phone')
                                    ->label('Phone'),
                            ]),

                        TextEntry::make('customer_address')
                            ->label('Address')
                            ->getStateUsing(function ($record): string {
                                $data = $record->customer_data ?? [];
                                $parts = array_filter([
                                    $data['address'] ?? null,
                                    $data['city'] ?? null,
                                    $data['state'] ?? null,
                                    $data['postcode'] ?? null,
                                    $data['country'] ?? null,
                                ]);

                                return implode(', ', $parts) ?: '-';
                            }),
                    ])
                    ->collapsible(),

                Section::make('Line Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Item'),

                                        TextEntry::make('quantity')
                                            ->label('Qty'),

                                        TextEntry::make('price')
                                            ->label('Price')
                                            ->money('MYR'),

                                        TextEntry::make('line_total')
                                            ->label('Total')
                                            ->getStateUsing(fn (array $state): float => ($state['quantity'] ?? 1) * ($state['price'] ?? 0)
                                            )
                                            ->money('MYR'),
                                    ]),

                                TextEntry::make('description')
                                    ->label('Description')
                                    ->placeholder('No description')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Amounts')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money(fn ($record): string => $record->currency),

                                TextEntry::make('tax_amount')
                                    ->label('Tax')
                                    ->money(fn ($record): string => $record->currency),

                                TextEntry::make('discount_amount')
                                    ->label('Discount')
                                    ->money(fn ($record): string => $record->currency),

                                TextEntry::make('total')
                                    ->label('Total')
                                    ->money(fn ($record): string => $record->currency)
                                    ->weight('bold')
                                    ->size('lg'),
                            ]),
                    ]),

                Section::make('Notes & Terms')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),

                        TextEntry::make('terms')
                            ->label('Terms & Conditions')
                            ->placeholder('No terms')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Template')
                    ->schema([
                        TextEntry::make('template.name')
                            ->label('Template Name')
                            ->placeholder('Default template'),

                        TextEntry::make('pdf_path')
                            ->label('PDF Path')
                            ->placeholder('No PDF generated')
                            ->copyable(),
                    ])
                    ->collapsible(),

                Section::make('Metadata')
                    ->schema([
                        KeyValueEntry::make('metadata')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record): bool => ! empty($record->metadata)),

                Section::make('Timestamps')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
