<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Schemas;

use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerUniqueRule;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\FilamentProducts\Support\CompositeComponentsFormData;
use AIArmada\FilamentProducts\Support\OwnerScopedRelationshipSelect;
use AIArmada\Products\Enums\CompositePriceMode;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class ProductForm
{
    public static function configure(Schema $schema): Schema
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
                                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => OwnerUniqueRule::scopeToOwner($rule, Product::class)),

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
                                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => OwnerUniqueRule::scopeToOwner($rule, Product::class))
                                    ->maxLength(100),

                                TextInput::make('barcode')
                                    ->label('Barcode (ISBN, UPC, GTIN, etc.)')
                                    ->maxLength(100),
                            ])
                            ->columns(2),

                        Section::make('Composite Package')
                            ->description('Components are the physical SKUs that make up this package.')
                            ->schema([
                                Select::make('price_mode')
                                    ->label('Price Mode')
                                    ->options(
                                        collect(CompositePriceMode::cases())
                                            ->mapWithKeys(fn (CompositePriceMode $mode) => [$mode->value => $mode->label()])
                                    )
                                    ->default(CompositePriceMode::Fixed->value)
                                    ->required()
                                    ->helperText('Fixed uses the package price above. Sum uses the component prices.'),

                                Repeater::make('components')
                                    ->label('Components')
                                    ->schema([
                                        Select::make('component_product_id')
                                            ->label('Component Product')
                                            ->options(fn (?Product $record): array => self::componentProductOptions($record))
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set) => $set('component_variant_id', null)),

                                        Select::make('component_variant_id')
                                            ->label('Variant')
                                            ->options(fn (Get $get): array => self::componentVariantOptions($get))
                                            ->searchable()
                                            ->placeholder('No variant'),

                                        TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(1)
                                            ->default(1)
                                            ->required(),

                                        TextInput::make('position')
                                            ->label('Position')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),
                                    ])
                                    ->columns(4)
                                    ->defaultItems(1)
                                    ->addActionLabel('Add component')
                                    ->default(function (?Product $record): array {
                                        if (! $record instanceof Product || ! self::isCompositeType($record->type)) {
                                            return [];
                                        }

                                        return CompositeComponentsFormData::fromProduct($record);
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn (Get $get): bool => self::isCompositeType($get('type'))),

                        Section::make('Shipping')
                            ->schema([
                                Toggle::make('requires_shipping')
                                    ->label('Requires shipping')
                                    ->default(ProductType::Simple->requiresShippingByDefault()),

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
                                    ->default('simple')
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        $type = ProductType::tryFrom((string) $state) ?? ProductType::Simple;

                                        $set('requires_shipping', $type->requiresShippingByDefault());
                                        $set('supports_variants', $type->supportsVariantsByDefault());
                                        $set('tracks_inventory', $type->tracksInventoryByDefault());

                                        if ($type === ProductType::Composite) {
                                            $set('price_mode', CompositePriceMode::Fixed->value);
                                        }
                                    }),

                                Toggle::make('supports_variants')
                                    ->label('Supports variants')
                                    ->helperText('Enable variants for dates, sizes, editions, or other purchasable sub-items.')
                                    ->default(ProductType::Simple->supportsVariantsByDefault()),

                                Toggle::make('tracks_inventory')
                                    ->label('Track inventory')
                                    ->helperText('Enable this when purchases should consume stock.')
                                    ->default(ProductType::Simple->tracksInventoryByDefault()),

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

                                        $query = OwnerQuery::applyToEloquentBuilder($query, $owner, (bool) config('customers.features.owner.include_global', false));

                                        $pattern = LikeSearch::contains($search);

                                        return $query
                                            ->where(function (Builder $query) use ($pattern): void {
                                                LikeSearch::whereLike($query, 'first_name', $pattern);
                                                LikeSearch::orWhereLike($query, 'last_name', $pattern);
                                                LikeSearch::orWhereLike($query, 'company', $pattern);
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

                                        $query = OwnerQuery::applyToEloquentBuilder($query, $owner, (bool) config('customers.features.owner.include_global', false));

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
                                        $result = ProductResource::calculatePriceResult($record, $get('pricing_customer_id'));

                                        if (! $result || ! $record instanceof Product) {
                                            return 'Save product to calculate.';
                                        }

                                        return ProductResource::formatCurrency($result->finalPrice, $record->currency);
                                    }),

                                Placeholder::make('discount_summary')
                                    ->label('Discount')
                                    ->content(function (?Product $record, Get $get): string {
                                        $result = ProductResource::calculatePriceResult($record, $get('pricing_customer_id'));

                                        if (! $result || $result->discountAmount <= 0) {
                                            return 'No discount';
                                        }

                                        $currency = $record?->currency ?? 'MYR';
                                        $amount = ProductResource::formatCurrency($result->discountAmount, $currency);

                                        return "{$amount} ({$result->discountPercentage}%)";
                                    }),

                                Placeholder::make('pricing_source')
                                    ->label('Applied Rule')
                                    ->content(function (?Product $record, Get $get): string {
                                        $result = ProductResource::calculatePriceResult($record, $get('pricing_customer_id'));

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
                                            $owner = OwnerContext::resolve();

                                            return OwnerQuery::applyToEloquentBuilder($query->select(['id', 'name']), $owner, (bool) config('products.features.owner.include_global', false));
                                        }
                                    )
                                    ->multiple()
                                    ->saveRelationshipsBeforeChildrenUsing(OwnerScopedRelationshipSelect::guard(Category::class))
                                    ->preload()
                                    ->searchable(),

                                (class_exists(SpatieTagsInput::class)
                                    ? SpatieTagsInput::make('tags')
                                    : TagsInput::make('tags'))
                                    ->label('Tags'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    private static function isCompositeType(mixed $type): bool
    {
        $productType = $type instanceof ProductType ? $type : ProductType::tryFrom((string) $type);

        return $productType === ProductType::Composite;
    }

    /**
     * @return array<string, string>
     */
    private static function componentProductOptions(?Product $record): array
    {
        $query = Product::query()
            ->whereIn('type', [ProductType::Simple->value, ProductType::Configurable->value])
            ->orderBy('name');

        if ($record instanceof Product && $record->exists) {
            $query->whereKeyNot($record->getKey());
        }

        return $query
            ->limit(100)
            ->get()
            ->mapWithKeys(static function (Product $product): array {
                $label = $product->name;

                if (is_string($product->sku) && $product->sku !== '') {
                    $label .= ' (' . $product->sku . ')';
                }

                return [(string) $product->getKey() => $label];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function componentVariantOptions(Get $get): array
    {
        $componentProductId = $get('component_product_id');

        if (! is_string($componentProductId) || $componentProductId === '') {
            return [];
        }

        return Variant::query()
            ->where('product_id', $componentProductId)
            ->where('is_enabled', true)
            ->orderBy('sku')
            ->get()
            ->mapWithKeys(static function (Variant $variant): array {
                $label = is_string($variant->sku) && $variant->sku !== ''
                    ? $variant->sku
                    : (string) $variant->getKey();

                return [(string) $variant->getKey() => $label];
            })
            ->all();
    }
}
