<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartItemResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CartItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Item Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('item_id')
                                    ->label('Item ID')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('name')
                                    ->label('Name')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('price')
                                    ->label('Price')
                                    ->prefix('RM')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->prefix('RM')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('associated_model')
                                    ->label('Associated Model')
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

                                TextInput::make('instance')
                                    ->label('Instance')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('identifier')
                                    ->label('Identifier')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),
            ]);
    }
}
