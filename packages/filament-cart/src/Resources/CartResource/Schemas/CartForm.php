<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CartForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Cart Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('identifier')
                                    ->label('Cart Identifier')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Unique identifier for this cart session'),

                                Select::make('instance')
                                    ->label('Cart Instance')
                                    ->options([
                                        'default' => 'Default Cart',
                                        'wishlist' => 'Wishlist',
                                        'comparison' => 'Comparison',
                                        'quote' => 'Quote Request',
                                        'bulk' => 'Bulk Order',
                                        'subscription' => 'Subscription',
                                    ])
                                    ->default('default')
                                    ->required()
                                    ->helperText('Type of cart instance'),
                            ]),
                    ]),

                Section::make('Cart Items')
                    ->schema([
                        Repeater::make('items')
                            ->label('Items')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('id')
                                            ->label('Product ID')
                                            ->required(),

                                        TextInput::make('name')
                                            ->label('Product Name')
                                            ->required()
                                            ->columnSpan(2),

                                        TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->required()
                                            ->default(1)
                                            ->minValue(1),
                                    ]),

                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('price')
                                            ->label('Price')
                                            ->numeric()
                                            ->prefix('$')
                                            ->required(),

                                        TextInput::make('subtotal')
                                            ->label('Subtotal')
                                            ->prefix('$')
                                            ->disabled()
                                            ->dehydrated(false),

                                        Fieldset::make('Item Attributes')
                                            ->schema([
                                                KeyValue::make('attributes')
                                                    ->label('Attributes')
                                                    ->keyLabel('Attribute')
                                                    ->valueLabel('Value')
                                                    ->reorderable(),
                                            ]),
                                    ]),
                            ])
                            ->collapsible()
                            ->cloneable()
                            ->reorderable()
                            ->itemLabel(fn (array $state): string => $state['name'] ?? 'New Item'
                            )
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Cart Conditions')
                    ->schema([
                        Repeater::make('conditions')
                            ->label('Conditions (Discounts, Taxes, etc.)')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Name')
                                            ->required(),

                                        Select::make('type')
                                            ->label('Type')
                                            ->options([
                                                'discount' => 'Discount',
                                                'tax' => 'Tax',
                                                'shipping' => 'Shipping',
                                                'fee' => 'Fee',
                                            ])
                                            ->required(),

                                        TextInput::make('value')
                                            ->label('Value')
                                            ->numeric()
                                            ->required(),
                                    ]),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->cloneable()
                            ->itemLabel(fn (array $state): string => $state['name'] ?? 'New Condition'
                            )
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Metadata')
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Additional Data')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
