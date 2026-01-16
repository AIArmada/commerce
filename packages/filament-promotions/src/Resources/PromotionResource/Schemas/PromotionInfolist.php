<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources\PromotionResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PromotionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->weight('bold'),

                                TextEntry::make('code')
                                    ->label('Promo Code')
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('Automatic'),

                                TextEntry::make('description')
                                    ->columnSpanFull()
                                    ->placeholder('No description'),
                            ]),
                    ]),

                Section::make('Discount Configuration')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('type')
                                    ->badge(),

                                TextEntry::make('discount_value')
                                    ->label('Discount Value')
                                    ->formatStateUsing(function ($record): string {
                                        if ($record->type->value === 'percentage') {
                                            return $record->discount_value . '%';
                                        }

                                        return '$' . number_format($record->discount_value / 100, 2);
                                    }),

                                TextEntry::make('min_order_value')
                                    ->label('Min Order')
                                    ->money('USD', divideBy: 100)
                                    ->placeholder('None'),

                                TextEntry::make('max_discount')
                                    ->label('Max Discount')
                                    ->money('USD', divideBy: 100)
                                    ->placeholder('Unlimited'),
                            ]),
                    ]),

                Section::make('Usage')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('usage_count')
                                    ->label('Total Uses')
                                    ->default(0),

                                TextEntry::make('usage_limit')
                                    ->label('Usage Limit')
                                    ->placeholder('Unlimited'),

                                TextEntry::make('usage_per_customer')
                                    ->label('Per Customer')
                                    ->placeholder('Unlimited'),

                                TextEntry::make('priority')
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ]),

                Section::make('Schedule')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('starts_at')
                                    ->label('Start Date')
                                    ->dateTime()
                                    ->placeholder('Immediate'),

                                TextEntry::make('ends_at')
                                    ->label('End Date')
                                    ->dateTime()
                                    ->placeholder('No end date'),
                            ]),
                    ]),

                Section::make('Status')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label('Active')
                                    ->boolean(),

                                IconEntry::make('is_stackable')
                                    ->label('Stackable')
                                    ->boolean(),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Targeting Conditions')
                    ->schema([
                        KeyValueEntry::make('conditions')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record): bool => ! empty($record->conditions)),
            ]);
    }
}
