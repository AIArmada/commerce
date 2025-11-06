<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartConditionResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CartConditionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Condition Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Name')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('type')
                                    ->label('Type')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('target')
                                    ->label('Target')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('value')
                                    ->label('Value')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('operator')
                                    ->label('Operator')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('parsed_value')
                                    ->label('Parsed Value')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('order')
                                    ->label('Order')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('level')
                                    ->label('Level')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Grid::make(5)
                            ->schema([
                                \Filament\Forms\Components\Checkbox::make('is_charge')
                                    ->label('Is Charge')
                                    ->disabled()
                                    ->dehydrated(false),

                                \Filament\Forms\Components\Checkbox::make('is_discount')
                                    ->label('Is Discount')
                                    ->disabled()
                                    ->dehydrated(false),

                                \Filament\Forms\Components\Checkbox::make('is_percentage')
                                    ->label('Is Percentage')
                                    ->disabled()
                                    ->dehydrated(false),

                                \Filament\Forms\Components\Checkbox::make('is_dynamic')
                                    ->label('Is Dynamic')
                                    ->disabled()
                                    ->dehydrated(false),

                                \Filament\Forms\Components\Checkbox::make('is_global')
                                    ->label('Is Global')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),

                Section::make('Cart Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('cart.identifier')
                                    ->label('Cart Identifier')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('item_id')
                                    ->label('Item ID')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn ($record) => $record?->isItemLevel()),
                            ]),
                    ]),
            ]);
    }
}
