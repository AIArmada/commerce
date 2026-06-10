<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PromotionResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PromotionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Promotion Details')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Promotion Name'),

                                TextEntry::make('code')
                                    ->label('Coupon Code')
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('No code'),

                                TextEntry::make('description')
                                    ->label('Description')
                                    ->columnSpanFull()
                                    ->placeholder('No description'),
                            ])
                            ->columns(2),

                        Section::make('Discount')
                            ->schema([
                                TextEntry::make('type')
                                    ->label('Discount Type')
                                    ->badge(),

                                TextEntry::make('discount_value')
                                    ->label('Discount Value')
                                    ->formatStateUsing(fn ($state, $record) => $record->type->formatValue($state)),

                                TextEntry::make('min_purchase_amount')
                                    ->label('Minimum Purchase')
                                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state) . ' cents' : null)
                                    ->placeholder('None'),

                                TextEntry::make('min_quantity')
                                    ->label('Minimum Quantity')
                                    ->placeholder('None'),
                            ])
                            ->columns(2),

                        Section::make('Scheduling')
                            ->schema([
                                TextEntry::make('starts_at')
                                    ->label('Start Date')
                                    ->dateTime('d M Y')
                                    ->placeholder('Not set'),

                                TextEntry::make('ends_at')
                                    ->label('End Date')
                                    ->dateTime('d M Y')
                                    ->placeholder('Not set'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Settings')
                            ->schema([
                                TextEntry::make('deactivated_at')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state === null ? 'Active' : 'Deactivated')
                                    ->color(fn ($state) => $state === null ? 'success' : 'danger'),

                                IconEntry::make('is_stackable')
                                    ->label('Stackable')
                                    ->boolean(),

                                TextEntry::make('priority')
                                    ->label('Priority')
                                    ->numeric(),
                            ]),

                        Section::make('Usage Limits')
                            ->schema([
                                TextEntry::make('usage_limit')
                                    ->label('Total Uses')
                                    ->placeholder('Unlimited'),

                                TextEntry::make('per_customer_limit')
                                    ->label('Uses Per Customer')
                                    ->placeholder('Unlimited'),

                                TextEntry::make('usage_count')
                                    ->label('Times Used')
                                    ->numeric(),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
