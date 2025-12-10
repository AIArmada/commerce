# Product Resource

> **Document:** 02 of 04  
> **Package:** `aiarmada/filament-products`  
> **Status:** Vision

---

## Overview

The ProductResource is the main admin interface for managing products, variants, and media.

---

## Resource Structure

```php
namespace AIArmada\FilamentProducts\Resources;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 1;
}
```

---

## Form Schema

```php
public static function form(Form $form): Form
{
    return $form->schema([
        // Left column (main content)
        Forms\Components\Group::make()->schema([
            Section::make('Basic Information')->schema([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, $state) => 
                        $set('slug', Str::slug($state))),
                TextInput::make('slug')->required(),
                RichEditor::make('description'),
                Textarea::make('short_description'),
            ]),

            Section::make('Media')->schema([
                SpatieMediaLibraryFileUpload::make('featured_image')
                    ->collection('featured')
                    ->image(),
                SpatieMediaLibraryFileUpload::make('gallery')
                    ->collection('gallery')
                    ->multiple()
                    ->reorderable(),
            ]),

            Section::make('Variants')->schema([
                // Variant builder (see below)
            ])->visible(fn ($get) => $get('type') === 'configurable'),
        ])->columnSpan(2),

        // Right column (sidebar)
        Forms\Components\Group::make()->schema([
            Section::make('Status')->schema([
                Select::make('status')
                    ->options(ProductStatus::class)
                    ->required(),
                Select::make('type')
                    ->options(ProductType::class)
                    ->required()
                    ->disabled(fn ($record) => $record?->exists),
                Toggle::make('is_featured'),
            ]),

            Section::make('Pricing')->schema([
                TextInput::make('price')
                    ->numeric()
                    ->prefix('RM')
                    ->required(),
                TextInput::make('compare_at_price')
                    ->numeric()
                    ->prefix('RM'),
                TextInput::make('cost_price')
                    ->numeric()
                    ->prefix('RM'),
            ]),

            Section::make('Organization')->schema([
                Select::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
                Select::make('tax_class_id')
                    ->relationship('taxClass', 'name'),
                TagsInput::make('tags'),
            ]),

            Section::make('SEO')->schema([
                TextInput::make('meta_title'),
                Textarea::make('meta_description'),
            ])->collapsed(),
        ])->columnSpan(1),
    ])->columns(3);
}
```

---

## Variant Builder

```php
Section::make('Variants')->schema([
    // Options repeater
    Repeater::make('options')
        ->relationship('options')
        ->schema([
            TextInput::make('name')
                ->required()
                ->placeholder('e.g., Size, Color'),
            TagsInput::make('values')
                ->placeholder('Add values and press Enter'),
        ])
        ->itemLabel(fn ($state) => $state['name'] ?? 'New Option')
        ->collapsible()
        ->maxItems(3),

    // Action to generate variants
    Actions::make([
        Action::make('generateVariants')
            ->label('Generate Variants')
            ->icon('heroicon-o-sparkles')
            ->action(fn ($livewire) => $livewire->generateVariants())
            ->requiresConfirmation(),
    ]),

    // Variants table
    TableRepeater::make('variants')
        ->relationship('variants')
        ->schema([
            TextInput::make('sku')->required(),
            TextInput::make('price')->numeric()->prefix('RM'),
            TextInput::make('stock')->numeric()->default(0),
            Toggle::make('is_active')->default(true),
        ])
        ->headers(['SKU', 'Price', 'Stock', 'Active'])
        ->default([]),
])
```

---

## Table Columns

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            SpatieMediaLibraryImageColumn::make('featured_image')
                ->collection('featured')
                ->circular(),
            TextColumn::make('name')
                ->searchable()
                ->sortable(),
            TextColumn::make('sku')
                ->searchable(),
            TextColumn::make('price')
                ->money('MYR')
                ->sortable(),
            TextColumn::make('type')
                ->badge(),
            TextColumn::make('status')
                ->badge()
                ->color(fn ($state) => $state->color()),
            TextColumn::make('categories.name')
                ->badge()
                ->separator(','),
            ToggleColumn::make('is_featured'),
        ])
        ->filters([
            SelectFilter::make('status')
                ->options(ProductStatus::class),
            SelectFilter::make('type')
                ->options(ProductType::class),
            SelectFilter::make('categories')
                ->relationship('categories', 'name')
                ->multiple(),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->action(fn ($record) => $record->duplicate()),
        ])
        ->bulkActions([
            Tables\Actions\BulkAction::make('activate')
                ->action(fn ($records) => $records->each->activate()),
            Tables\Actions\BulkAction::make('deactivate')
                ->action(fn ($records) => $records->each->deactivate()),
            Tables\Actions\DeleteBulkAction::make(),
        ]);
}
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-category-resource.md](03-category-resource.md)
