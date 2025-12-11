<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\ProductResource\Pages;
use AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', ProductStatus::Active)->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Product Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Product Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Forms\Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state))
                                    ),

                                Forms\Components\TextInput::make('slug')
                                    ->label('URL Slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\MarkdownEditor::make('description')
                                    ->label('Description')
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('short_description')
                                    ->label('Short Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Pricing')
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->required()
                                    ->minValue(0),

                                Forms\Components\TextInput::make('compare_price')
                                    ->label('Compare at Price')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->helperText('Original price before discount'),

                                Forms\Components\TextInput::make('cost')
                                    ->label('Cost per Item')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->helperText('For profit calculation'),
                            ])
                            ->columns(3),

                        Forms\Components\Section::make('Inventory')
                            ->schema([
                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU')
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('barcode')
                                    ->label('Barcode (ISBN, UPC, GTIN, etc.)')
                                    ->maxLength(100),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Shipping')
                            ->schema([
                                Forms\Components\Toggle::make('requires_shipping')
                                    ->label('This is a physical product')
                                    ->default(true),

                                Forms\Components\TextInput::make('weight')
                                    ->label('Weight')
                                    ->numeric()
                                    ->suffix('kg')
                                    ->visible(fn (Forms\Get $get) => $get('requires_shipping')),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('length')
                                            ->label('Length')
                                            ->numeric()
                                            ->suffix('cm'),

                                        Forms\Components\TextInput::make('width')
                                            ->label('Width')
                                            ->numeric()
                                            ->suffix('cm'),

                                        Forms\Components\TextInput::make('height')
                                            ->label('Height')
                                            ->numeric()
                                            ->suffix('cm'),
                                    ])
                                    ->visible(fn (Forms\Get $get) => $get('requires_shipping')),
                            ]),

                        Forms\Components\Section::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('meta_title')
                                    ->label('Meta Title')
                                    ->maxLength(70)
                                    ->helperText('Leave blank to use product name'),

                                Forms\Components\Textarea::make('meta_description')
                                    ->label('Meta Description')
                                    ->rows(3)
                                    ->maxLength(160),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Status')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options(
                                        collect(ProductStatus::cases())
                                            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                                    )
                                    ->required()
                                    ->default('draft'),

                                Forms\Components\Select::make('visibility')
                                    ->label('Visibility')
                                    ->options(
                                        collect(ProductVisibility::cases())
                                            ->mapWithKeys(fn ($visibility) => [$visibility->value => $visibility->label()])
                                    )
                                    ->default('catalog_search'),
                            ]),

                        Forms\Components\Section::make('Product Type')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Type')
                                    ->options(
                                        collect(ProductType::cases())
                                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                                    )
                                    ->required()
                                    ->default('simple'),

                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Featured Product'),

                                Forms\Components\Toggle::make('is_taxable')
                                    ->label('Charge Tax')
                                    ->default(true),
                            ]),

                        Forms\Components\Section::make('Media')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('hero')
                                    ->collection('hero')
                                    ->label('Featured Image')
                                    ->image()
                                    ->imageEditor()
                                    ->responsiveImages(),

                                SpatieMediaLibraryFileUpload::make('gallery')
                                    ->collection('gallery')
                                    ->label('Gallery')
                                    ->image()
                                    ->multiple()
                                    ->reorderable()
                                    ->imageEditor()
                                    ->responsiveImages()
                                    ->maxFiles(20),
                            ]),

                        Forms\Components\Section::make('Organization')
                            ->schema([
                                Forms\Components\Select::make('categories')
                                    ->label('Categories')
                                    ->relationship('categories', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),

                                SpatieTagsInput::make('tags')
                                    ->label('Tags'),

                                SpatieTagsInput::make('colors')
                                    ->label('Colors')
                                    ->type('colors'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('hero')
                    ->collection('hero')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->sku),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        ProductType::Simple => 'gray',
                        ProductType::Configurable => 'info',
                        ProductType::Bundle => 'warning',
                        ProductType::Digital => 'success',
                        ProductType::Subscription => 'primary',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(),

                SpatieTagsColumn::make('tags')
                    ->label('Tags')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(
                        collect(ProductStatus::cases())
                            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                    ),

                Tables\Filters\SelectFilter::make('type')
                    ->options(
                        collect(ProductType::cases())
                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                    ),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Tables\Filters\SelectFilter::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (Product $record) {
                        $newProduct = $record->replicate();
                        $newProduct->name = $record->name . ' (Copy)';
                        $newProduct->slug = $record->slug . '-copy-' . time();
                        $newProduct->sku = $record->sku ? $record->sku . '-COPY' : null;
                        $newProduct->status = ProductStatus::Draft;
                        $newProduct->save();

                        return redirect(static::getUrl('edit', ['record' => $newProduct]));
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->update(['status' => ProductStatus::Active])
                        ),
                    Tables\Actions\BulkAction::make('draft')
                        ->label('Set to Draft')
                        ->icon('heroicon-o-pencil')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->update(['status' => ProductStatus::Draft])
                        ),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Product Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('sku')
                            ->label('SKU')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label()),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => $state->color()),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Pricing')
                    ->schema([
                        Infolists\Components\TextEntry::make('price')
                            ->money('MYR', divideBy: 100),
                        Infolists\Components\TextEntry::make('compare_price')
                            ->money('MYR', divideBy: 100)
                            ->visible(fn ($record) => $record->compare_price),
                        Infolists\Components\TextEntry::make('cost')
                            ->money('MYR', divideBy: 100)
                            ->visible(fn ($record) => $record->cost),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Description')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VariantsRelationManager::class,
            RelationManagers\OptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'description'];
    }
}
