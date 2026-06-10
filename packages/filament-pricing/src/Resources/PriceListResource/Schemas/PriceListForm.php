<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class PriceListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Price List Details')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Set $set, ?string $state) => $set('slug', Str::slug($state))
                                    ),

                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                Select::make('currency')
                                    ->label('Currency')
                                    ->options([
                                        'MYR' => 'MYR - Malaysian Ringgit',
                                        'USD' => 'USD - US Dollar',
                                        'SGD' => 'SGD - Singapore Dollar',
                                    ])
                                    ->default('MYR')
                                    ->required(),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
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
                                DateTimePicker::make('deactivated_at')
                                    ->label('Deactivated At')
                                    ->helperText('Set a date to deactivate this price list. Leave empty for active.'),

                                Toggle::make('is_default')
                                    ->label('Default Price List')
                                    ->helperText('Used when no other price list applies'),

                                TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher = more priority'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
