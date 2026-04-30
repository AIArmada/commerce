<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages;
use AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Pricing\Models\Price;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Throwable;

final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-products.navigation.group', 'Catalog');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-products.navigation.resources.products', 1);
    }

    /**
     * @return Builder<Product>
     */
    public static function getEloquentQuery(): Builder
    {
        return Product::query()
            ->forOwner();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', ProductStatus::Active)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Product Information')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Product Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Set $set, ?string $state) => $set('slug', Str::slug($state))
                                    ),

                                TextInput::make('slug')
                                    ->label('URL Slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                MarkdownEditor::make('description')
                                    ->label('Description')
                                    ->columnSpanFull(),

                                Textarea::make('short_description')
                                    ->label('Short Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Pricing')
                            ->schema([
                                TextInput::make('price')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->required()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->formatStateUsing(fn (?int $state): ?float => $state === null ? null : $state / 100)
                                    ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),

                                TextInput::make('compare_price')
                                    ->label('Compare at Price')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->helperText('Original price before discount')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->formatStateUsing(fn (?int $state): ?float => $state === null ? null : $state / 100)
                                    ->dehydrateStateUsing(fn (?string $state): ?int => $state === null ? null : (int) round(((float) $state) * 100)),

                                TextInput::make('cost')
                                    ->label('Cost per Item')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->helperText('For profit calculation')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->formatStateUsing(fn (?int $state): ?float => $state === null ? null : $state / 100)
                                    ->dehydrateStateUsing(fn (?string $state): ?int => $state === null ? null : (int) round(((float) $state) * 100)),
                            ])
                            ->columns(3),

                        Section::make('Inventory')
                            ->schema([
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(100),

                                TextInput::make('barcode')
                                    ->label('Barcode (ISBN, UPC, GTIN, etc.)')
                                    ->maxLength(100),
                            ])
                            ->columns(2),

                        Section::make('Shipping')
                            ->schema([
                                Toggle::make('requires_shipping')
                                    ->label('This is a physical product')
                                    ->default(true),

                                TextInput::make('weight')
                                    ->label('Weight')
                                    ->numeric()
                                    ->suffix('kg')
                                    ->visible(fn (Get $get) => $get('requires_shipping')),

                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('length')
                                            ->label('Length')
                                            ->numeric()
                                            ->suffix('cm'),

                                        TextInput::make('width')
                                            ->label('Width')
                                            ->numeric()
                                            ->suffix('cm'),

                                        TextInput::make('height')
                                            ->label('Height')
                                            ->numeric()
                                            ->suffix('cm'),
                                    ])
                                    ->visible(fn (Get $get) => $get('requires_shipping')),
                            ]),

                        Section::make('SEO')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('Meta Title')
                                    ->maxLength(70)
                                    ->helperText('Leave blank to use product name'),

                                Textarea::make('meta_description')
                                    ->label('Meta Description')
                                    ->rows(3)
                                    ->maxLength(160),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options(
                                        collect(ProductStatus::cases())
                                            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                                    )
                                    ->required()
                                    ->default('draft'),

                                Select::make('visibility')
                                    ->label('Visibility')
                                    ->options(
                                        collect(ProductVisibility::cases())
                                            ->mapWithKeys(fn ($visibility) => [$visibility->value => $visibility->label()])
                                    )
                                    ->default('catalog_search'),
                            ]),

                        Section::make('Product Type')
                            ->schema([
                                Select::make('type')
                                    ->label('Type')
                                    ->options(
                                        collect(ProductType::cases())
                                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                                    )
                                    ->required()
                                    ->default('simple'),

                                Toggle::make('is_featured')
                                    ->label('Featured Product'),

                                Toggle::make('is_taxable')
                                    ->label('Charge Tax')
                                    ->default(true),
                            ]),

                        Section::make('Calculated Pricing')
                            ->schema([
                                Select::make('pricing_customer_id')
                                    ->label('Customer (Optional)')
                                    ->searchable()
                                    ->helperText('Use a customer context for pricing rules.')
                                    ->visible(fn (): bool => class_exists(Customer::class))
                                    ->dehydrated(false)
                                    ->live()
                                    ->getSearchResultsUsing(function (string $search): array {
                                        if (! class_exists(Customer::class)) {
                                            return [];
                                        }

                                        $owner = OwnerContext::resolve();
                                        $query = Customer::query();

                                        if (method_exists(Customer::class, 'scopeForOwner')) {
                                            $query->forOwner($owner);
                                        }

                                        return $query
                                            ->where(function (Builder $query) use ($search): void {
                                                $query
                                                    ->where('full_name', 'like', "%{$search}%")
                                                    ->orWhere('email', 'like', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(function (Customer $customer): array {
                                                $fullName = (string) $customer->getAttribute('full_name');
                                                $email = (string) $customer->getAttribute('email');

                                                return [
                                                    (string) $customer->getKey() => $fullName . ' (' . $email . ')',
                                                ];
                                            })
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value): ?string {
                                        if ($value === null || ! class_exists(Customer::class)) {
                                            return null;
                                        }

                                        $owner = OwnerContext::resolve();
                                        $query = Customer::query();

                                        if (method_exists(Customer::class, 'scopeForOwner')) {
                                            $query->forOwner($owner);
                                        }

                                        $customer = $query
                                            ->whereKey($value)
                                            ->first();

                                        if (! $customer instanceof Customer) {
                                            return null;
                                        }

                                        $fullName = (string) $customer->getAttribute('full_name');
                                        $email = (string) $customer->getAttribute('email');

                                        return $fullName . ' (' . $email . ')';
                                    }),

                                Placeholder::make('calculated_price')
                                    ->label('Calculated Price')
                                    ->content(function (?Product $record, Get $get): string {
                                        $result = self::calculatePriceResult($record, $get('pricing_customer_id'));

                                        if (! $result || ! $record instanceof Product) {
                                            return 'Save product to calculate.';
                                        }

                                        return self::formatCurrency($result->finalPrice, $record->currency);
                                    }),

                                Placeholder::make('discount_summary')
                                    ->label('Discount')
                                    ->content(function (?Product $record, Get $get): string {
                                        $result = self::calculatePriceResult($record, $get('pricing_customer_id'));

                                        if (! $result || $result->discountAmount <= 0) {
                                            return 'No discount';
                                        }

                                        $currency = $record?->currency ?? 'MYR';
                                        $amount = self::formatCurrency($result->discountAmount, $currency);

                                        return "{$amount} ({$result->discountPercentage}%)";
                                    }),

                                Placeholder::make('pricing_source')
                                    ->label('Applied Rule')
                                    ->content(function (?Product $record, Get $get): string {
                                        $result = self::calculatePriceResult($record, $get('pricing_customer_id'));

                                        if (! $result) {
                                            return '—';
                                        }

                                        return $result->promotionName
                                            ?? $result->priceListName
                                            ?? $result->tierDescription
                                            ?? $result->discountSource
                                            ?? 'Base Price';
                                    }),
                            ])
                            ->visible(fn (?Product $record): bool => $record instanceof Product)
                            ->columns(1),

                        Section::make('Media')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('hero')
                                    ->label('Featured Image')
                                    ->collection('hero')
                                    ->image()
                                    ->imageEditor()
                                    ->acceptedFileTypes(config('products.media.collections.hero.mimes', []))
                                    ->maxFiles((int) config('products.media.collections.hero.limit', 1)),

                                SpatieMediaLibraryFileUpload::make('gallery')
                                    ->label('Gallery')
                                    ->collection('gallery')
                                    ->image()
                                    ->multiple()
                                    ->reorderable()
                                    ->imageEditor()
                                    ->acceptedFileTypes(config('products.media.collections.gallery.mimes', []))
                                    ->maxFiles((int) config('products.media.collections.gallery.limit', 20)),
                            ]),

                        Section::make('Organization')
                            ->schema([
                                Select::make('categories')
                                    ->label('Categories')
                                    ->relationship(
                                        'categories',
                                        'name',
                                        modifyQueryUsing: function (Builder $query): Builder {
                                            /** @var Builder<Category> $query */
                                            return $query->forOwner();
                                        }
                                    )
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),

                                SpatieTagsInput::make('tags')
                                    ->label('Tags'),
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
                Tables\Columns\SpatieMediaLibraryImageColumn::make('hero')
                    ->collection('hero')
                    ->conversion('thumbnail')
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
                    ->formatStateUsing(fn (ProductType | string | null $state): string => ($state instanceof ProductType ? $state : ProductType::tryFrom((string) $state))?->label() ?? '—')
                    ->color(fn (ProductType | string | null $state): string => match ($state instanceof ProductType ? $state : ProductType::tryFrom((string) $state)) {
                        ProductType::Simple => 'gray',
                        ProductType::Configurable => 'info',
                        ProductType::Bundle => 'warning',
                        ProductType::Digital => 'success',
                        ProductType::Subscription => 'primary',
                        null => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money(fn (Product $record): string => $record->currency, divideBy: 100)
                    ->sortable()
                    ->alignEnd()
                    ->description(function (Product $record): ?string {
                        if (! class_exists(Price::class)) {
                            return null;
                        }

                        $pricesCount = $record->prices()->count();

                        if ($pricesCount === 0) {
                            return null;
                        }

                        $activePricesCount = $record->prices()
                            ->whereHas('priceList', fn ($q) => $q->where('is_active', true))
                            ->count();

                        return "{$activePricesCount} of {$pricesCount} price lists";
                    }),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(),

                SpatieTagsColumn::make('tags')
                    ->label('Tags')
                    ->badge()
                    ->separator(',')
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
                    ->relationship(
                        'categories',
                        'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            /** @var Builder<Category> $query */
                            return $query->select(['id', 'name'])->forOwner()->groupBy('id', 'name');
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->authorize(fn (Product $record): bool => auth()->user()?->can('duplicate', $record) ?? false)
                    ->action(function (Product $record) {
                        try {
                            $newProduct = $record->replicate();
                            $newProduct->name = $record->name . ' (Copy)';
                            $newProduct->slug = $record->slug . '-copy-' . time();
                            $newProduct->sku = $record->sku ? $record->sku . '-COPY' : null;
                            $newProduct->status = ProductStatus::Draft;
                            $newProduct->save();

                            return redirect(static::getUrl('edit', ['record' => $newProduct]));
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Duplicate failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => auth()->user()?->can('updateAny', Product::class) ?? false)
                        ->action(
                            fn (Collection $records) => $records->each->update(['status' => ProductStatus::Active])
                        ),
                    BulkAction::make('draft')
                        ->label('Set to Draft')
                        ->icon('heroicon-o-pencil')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => auth()->user()?->can('updateAny', Product::class) ?? false)
                        ->action(
                            fn (Collection $records) => $records->each->update(['status' => ProductStatus::Draft])
                        ),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Product Details')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('sku')
                            ->label('SKU')
                            ->copyable(),
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => $state->color()),
                    ])
                    ->columns(4),

                Section::make('Pricing')
                    ->schema([
                        TextEntry::make('price')
                            ->money(fn (Product $record): string => $record->currency, divideBy: 100),
                        TextEntry::make('compare_price')
                            ->money(fn (Product $record): string => $record->currency, divideBy: 100)
                            ->visible(fn ($record) => $record->compare_price),
                        TextEntry::make('cost')
                            ->money(fn (Product $record): string => $record->currency, divideBy: 100)
                            ->visible(fn ($record) => $record->cost),
                    ])
                    ->columns(3),

                Section::make('Calculated Pricing')
                    ->schema([
                        TextEntry::make('calculated_price')
                            ->label('Calculated Price')
                            ->state(function (Product $record): string {
                                $result = self::calculatePriceResult($record, null);

                                if (! $result) {
                                    return '—';
                                }

                                return self::formatCurrency($result->finalPrice, $record->currency);
                            }),
                        TextEntry::make('calculated_discount')
                            ->label('Discount')
                            ->state(function (Product $record): string {
                                $result = self::calculatePriceResult($record, null);

                                if (! $result || $result->discountAmount <= 0) {
                                    return 'No discount';
                                }

                                $amount = self::formatCurrency($result->discountAmount, $record->currency);

                                return "{$amount} ({$result->discountPercentage}%)";
                            }),
                        TextEntry::make('calculated_source')
                            ->label('Applied Rule')
                            ->state(function (Product $record): string {
                                $result = self::calculatePriceResult($record, null);

                                return $result?->promotionName
                                    ?? $result?->priceListName
                                    ?? $result?->tierDescription
                                    ?? $result?->discountSource
                                    ?? 'Base Price';
                            }),
                    ])
                    ->columns(3),

                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    private static function calculatePriceResult(?Product $record, ?string $customerId): ?PriceResultData
    {
        if (! $record instanceof Product) {
            return null;
        }

        try {
            $calculator = app(PriceCalculatorInterface::class);
            $context = ['currency' => $record->currency];

            if (is_string($customerId) && $customerId !== '') {
                $context['customer_id'] = $customerId;
            }

            return $calculator->calculate($record, 1, $context);
        } catch (Throwable) {
            return null;
        }
    }

    private static function formatCurrency(int $amountMinor, ?string $currency): string
    {
        return Number::currency($amountMinor / 100, $currency ?? 'MYR');
    }

    public static function getRelations(): array
    {
        $relations = [
            RelationManagers\VariantsRelationManager::class,
            RelationManagers\OptionsRelationManager::class,
        ];

        // Add prices relation manager if pricing package is installed
        if (class_exists(Price::class)) {
            $relations[] = RelationManagers\PricesRelationManager::class;
        }

        return $relations;
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
