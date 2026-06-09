<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PromotionResource\Schemas;

use AIArmada\Promotions\Enums\PromotionType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Promotion Details')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Promotion Name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('code')
                                    ->label('Coupon Code')
                                    ->helperText('Optional code for coupon-based promotions')
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Discount')
                            ->schema([
                                Select::make('type')
                                    ->label('Discount Type')
                                    ->options(
                                        collect(PromotionType::cases())
                                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                                    )
                                    ->required()
                                    ->default('percentage')
                                    ->live(),

                                TextInput::make('discount_value')
                                    ->label(fn (Get $get) => match ($get('type')) {
                                        'percentage' => 'Discount Percentage (%)',
                                        'fixed' => 'Discount Amount (cents)',
                                        default => 'Value',
                                    })
                                    ->numeric()
                                    ->required(),

                                TextInput::make('min_purchase_amount')
                                    ->label('Minimum Purchase (cents)')
                                    ->numeric()
                                    ->helperText('Minimum order value to apply'),

                                TextInput::make('min_quantity')
                                    ->label('Minimum Quantity')
                                    ->numeric()
                                    ->helperText('Minimum items in cart'),
                            ])
                            ->columns(2),

                        Section::make('Scheduling')
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label('Start Date'),

                                DateTimePicker::make('ends_at')
                                    ->label('End Date'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Settings')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Toggle::make('is_stackable')
                                    ->label('Stackable')
                                    ->helperText('Can combine with other promotions'),

                                TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher = apply first'),
                            ]),

                        Section::make('Usage Limits')
                            ->schema([
                                TextInput::make('usage_limit')
                                    ->label('Total Uses')
                                    ->numeric()
                                    ->helperText('Leave empty for unlimited'),

                                TextInput::make('per_customer_limit')
                                    ->label('Uses Per Customer')
                                    ->numeric()
                                    ->helperText('Leave empty for unlimited'),

                                Placeholder::make('usage_count')
                                    ->label('Times Used')
                                    ->content(fn ($record) => $record?->usage_count ?? 0),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
