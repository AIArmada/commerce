<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PriceListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                Section::make('Price List Details')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(
                                                fn (Forms\Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state))
                                            ),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->maxLength(100)
                                            ->unique(ignoreRecord: true),

                                        Forms\Components\Select::make('currency')
                                            ->label('Currency')
                                            ->options([
                                                'MYR' => 'MYR - Malaysian Ringgit',
                                                'USD' => 'USD - US Dollar',
                                                'SGD' => 'SGD - Singapore Dollar',
                                            ])
                                            ->default('MYR')
                                            ->required(),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Description')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                Section::make('Scheduling')
                                    ->schema([
                                        Forms\Components\DateTimePicker::make('starts_at')
                                            ->label('Start Date'),

                                        Forms\Components\DateTimePicker::make('ends_at')
                                            ->label('End Date'),
                                    ])
                                    ->columns(2),
                            ])
                            ->columnSpan(['lg' => 2]),

                        Grid::make(1)
                            ->schema([
                                Section::make('Settings')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true),

                                        Forms\Components\Toggle::make('is_default')
                                            ->label('Default Price List')
                                            ->helperText('Used when no other price list applies'),

                                        Forms\Components\TextInput::make('priority')
                                            ->label('Priority')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('Higher = more priority'),
                                    ]),
                            ])
                            ->columnSpan(['lg' => 1]),
                    ]),
            ]);
    }
}
