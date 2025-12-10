# Price List Resource

> **Document:** 02 of 03  
> **Package:** `aiarmada/filament-pricing`  
> **Status:** Vision

---

## Overview

The PriceListResource enables management of price lists, individual prices, and price rules.

---

## Price List Form

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Section::make('Basic Information')->schema([
            TextInput::make('name')->required(),
            TextInput::make('code')
                ->required()
                ->unique(ignoreRecord: true),
            Textarea::make('description'),
        ]),

        Section::make('Settings')->schema([
            Select::make('currency')
                ->options(['MYR' => 'MYR', 'USD' => 'USD', 'SGD' => 'SGD'])
                ->required(),
            TextInput::make('priority')
                ->numeric()
                ->default(0),
            Toggle::make('is_active')->default(true),
        ])->columns(3),

        Section::make('Assignment')->schema([
            Select::make('segments')
                ->relationship('segments', 'name')
                ->multiple()
                ->preload()
                ->helperText('Leave empty to apply to all customers'),
            Select::make('customerGroups')
                ->relationship('customerGroups', 'name')
                ->multiple()
                ->preload(),
        ])->columns(2),

        Section::make('Schedule')->schema([
            DateTimePicker::make('starts_at'),
            DateTimePicker::make('ends_at'),
        ])->columns(2),
    ]);
}
```

---

## Price Entry Management

```php
class ManagePrices extends RelationManager
{
    protected static string $relationship = 'prices';

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('priceable_type')
                ->options([
                    Product::class => 'Product',
                    Variant::class => 'Variant',
                ])
                ->required()
                ->live(),
            Select::make('priceable_id')
                ->options(fn ($get) => match ($get('priceable_type')) {
                    Product::class => Product::pluck('name', 'id'),
                    Variant::class => Variant::with('product')
                        ->get()
                        ->mapWithKeys(fn ($v) => [$v->id => "{$v->product->name} - {$v->sku}"]),
                    default => [],
                })
                ->searchable()
                ->required(),
            TextInput::make('price')
                ->numeric()
                ->prefix('RM')
                ->required(),
            TextInput::make('compare_at_price')
                ->numeric()
                ->prefix('RM'),
            TextInput::make('min_quantity')
                ->numeric()
                ->default(1),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('priceable.name')->label('Product'),
                TextColumn::make('price')->money('MYR'),
                TextColumn::make('compare_at_price')->money('MYR'),
                TextColumn::make('min_quantity'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
```

---

## Price Rule Builder

```php
class PriceRuleResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Rule Information')->schema([
                TextInput::make('name')->required(),
                Textarea::make('description'),
                TextInput::make('priority')->numeric()->default(0),
            ]),

            Section::make('Conditions')->schema([
                Repeater::make('conditions')
                    ->schema([
                        Select::make('field')
                            ->options([
                                'customer_segment' => 'Customer Segment',
                                'customer_group' => 'Customer Group',
                                'cart_subtotal' => 'Cart Subtotal',
                                'cart_quantity' => 'Cart Quantity',
                                'product_category' => 'Product Category',
                                'product_tag' => 'Product Tag',
                            ])
                            ->required()
                            ->live(),
                        Select::make('operator')
                            ->options(['=' => 'is', '!=' => 'is not', '>' => '>', '<' => '<', '>=' => '>=', '<=' => '<='])
                            ->required(),
                        TextInput::make('value')->required(),
                    ])
                    ->columns(3),
            ]),

            Section::make('Actions')->schema([
                Select::make('action_type')
                    ->options([
                        'percent_discount' => 'Percentage Discount',
                        'fixed_discount' => 'Fixed Discount',
                        'fixed_price' => 'Fixed Price',
                    ])
                    ->required()
                    ->live(),
                TextInput::make('action_value')
                    ->numeric()
                    ->required()
                    ->prefix(fn ($get) => match ($get('action_type')) {
                        'fixed_discount', 'fixed_price' => 'RM',
                        default => '',
                    })
                    ->suffix(fn ($get) => $get('action_type') === 'percent_discount' ? '%' : ''),
            ])->columns(2),

            Section::make('Options')->schema([
                Toggle::make('is_active')->default(true),
                Toggle::make('is_stackable')
                    ->helperText('Allow combining with other rules'),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
            ])->columns(4),
        ]);
    }
}
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-dashboard-widgets.md](03-dashboard-widgets.md)
