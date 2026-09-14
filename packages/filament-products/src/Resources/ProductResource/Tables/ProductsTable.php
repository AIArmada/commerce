<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Tables;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\Pricing\Models\Price;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use BackedEnum;
use Carbon\CarbonImmutable;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProductsTable
{
    public static function configure(Table $table): Table
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

                        $pricesCount = $record->getAttribute('prices_count');
                        $pricesCount = is_numeric($pricesCount) ? (int) $pricesCount : $record->prices()->count();

                        if ($pricesCount === 0) {
                            return null;
                        }

                        $activePricesCount = $record->getAttribute('active_prices_count');
                        $activePricesCount = is_numeric($activePricesCount)
                            ? (int) $activePricesCount
                            : $record->prices()
                                ->whereHas('priceList', fn ($q) => $q->where('is_active', true))
                                ->count();

                        return "{$activePricesCount} of {$pricesCount} price lists";
                    }),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('supports_variants')
                    ->label('Variants')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('tracks_inventory')
                    ->label('Inventory')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                (class_exists(SpatieTagsColumn::class)
                    ? SpatieTagsColumn::make('tags')
                    : Tables\Columns\TextColumn::make('tags')->state(fn (Product $record): string => $record->tags->pluck('name')->implode(', ')))
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
            ->headerActions([
                Action::make('import')
                    ->label('Import')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['product.create', 'product.update']))
                    ->form([
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'text/plain'])
                            ->required()
                            ->disk('local')
                            ->directory('imports')
                            ->maxSize((int) config('filament-products.import.max_file_kb', 10240))
                            ->helperText('Upload a CSV file with product data'),

                        Forms\Components\Toggle::make('update_existing')
                            ->label('Update Existing Products')
                            ->helperText('Update products that already exist (matched by SKU)')
                            ->default(false),

                        Forms\Components\Toggle::make('skip_errors')
                            ->label('Skip Errors')
                            ->helperText('Continue importing even if some rows have errors')
                            ->default(true),
                    ])
                    ->action(function (array $data): void {
                        self::importProducts($data);
                    }),

                Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['product.viewAny']))
                    ->form([
                        Forms\Components\CheckboxList::make('fields')
                            ->label('Select Fields to Export')
                            ->options([
                                'name' => 'Name',
                                'sku' => 'SKU',
                                'slug' => 'Slug',
                                'description' => 'Description',
                                'short_description' => 'Short Description',
                                'currency' => 'Currency',
                                'price' => 'Price',
                                'compare_price' => 'Compare Price',
                                'cost' => 'Cost',
                                'weight' => 'Weight',
                                'status' => 'Status',
                                'type' => 'Type',
                                'visibility' => 'Visibility',
                                'is_featured' => 'Featured',
                                'is_taxable' => 'Taxable',
                                'requires_shipping' => 'Requires Shipping',
                                'tax_class' => 'Tax Class',
                            ])
                            ->default(['name', 'sku', 'currency', 'price', 'status', 'type'])
                            ->required()
                            ->columns(3),

                        Forms\Components\Select::make('status_filter')
                            ->label('Filter by Status')
                            ->options([
                                'all' => 'All Products',
                                ...collect(ProductStatus::cases())
                                    ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                                    ->all(),
                            ])
                            ->default('all'),
                    ])
                    ->action(function (array $data) {
                        return self::exportProducts($data);
                    }),

                Action::make('download_template')
                    ->label('Download CSV Template')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['product.create']))
                    ->action(function () {
                        return self::downloadTemplate();
                    }),
            ])
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
                            $owner = OwnerContext::resolve();

                            return OwnerQuery::applyToEloquentBuilder($query->select(['id', 'name']), $owner)->groupBy('id', 'name');
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
                            $newProduct = self::duplicateProduct($record);
                            self::duplicateProductMedia($record, $newProduct);

                            return redirect(ProductResource::getUrl('edit', ['record' => $newProduct]));
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
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => FilamentPermission::hasAbility('product.delete')),
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => auth()->user()?->can('updateAny', Product::class) ?? false)
                        ->action(function (Collection $records): void {
                            $records->each(function (Product $record): void {
                                $record->update(['status' => ProductStatus::Active]);
                            });
                        }),
                    BulkAction::make('draft')
                        ->label('Set to Draft')
                        ->icon('heroicon-o-pencil')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => auth()->user()?->can('updateAny', Product::class) ?? false)
                        ->action(function (Collection $records): void {
                            $records->each(function (Product $record): void {
                                $record->update(['status' => ProductStatus::Draft]);
                            });
                        }),

                    BulkAction::make('update_price')
                        ->label('Update Price')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('success')
                        ->authorize(fn (): bool => FilamentPermission::hasAbility('product.update'))
                        ->form([
                            Forms\Components\Radio::make('price_action')
                                ->label('Action')
                                ->options([
                                    'set' => 'Set to specific value',
                                    'increase_percent' => 'Increase by percentage',
                                    'decrease_percent' => 'Decrease by percentage',
                                    'increase_amount' => 'Increase by amount',
                                    'decrease_amount' => 'Decrease by amount',
                                ])
                                ->required()
                                ->live()
                                ->default('set'),

                            Forms\Components\TextInput::make('value')
                                ->label(function (Get $get) {
                                    $currency = mb_strtoupper((string) config('products.defaults.currency', 'MYR'));

                                    return match ($get('price_action')) {
                                        'set' => "New Price ({$currency})",
                                        'increase_percent', 'decrease_percent' => 'Percentage (%)',
                                        'increase_amount', 'decrease_amount' => "Amount ({$currency})",
                                        default => 'Value',
                                    };
                                })
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(fn (Get $get): int | float => in_array($get('price_action'), ['increase_percent', 'decrease_percent'], true) ? 100 : 1000000),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $adjustments = self::priceAdjustments($data['price_action'] ?? '', $data['value'] ?? 0);

                            DB::transaction(static function () use ($records, $adjustments): void {
                                foreach ($records as $product) {
                                    $currentMinor = (int) $product->getAttribute('price');

                                    $newMinor = match ($adjustments['mode']) {
                                        'set' => $adjustments['minor'],
                                        'percent' => (int) round($currentMinor * $adjustments['factor']),
                                        'amount' => $currentMinor + $adjustments['minor'],
                                        default => $currentMinor,
                                    };

                                    $product->update(['price' => max(0, $newMinor)]);
                                }
                            });

                            Notification::make()
                                ->title('Prices updated')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('update_visibility')
                        ->label('Change Visibility')
                        ->icon('heroicon-o-eye')
                        ->authorize(fn (): bool => FilamentPermission::hasAbility('product.update'))
                        ->form([
                            Forms\Components\Select::make('visibility')
                                ->label('New Visibility')
                                ->options(ProductVisibility::class)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $product) {
                                $product->update(['visibility' => $data['visibility']]);
                            }

                            Notification::make()
                                ->title('Visibility updated')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('assign_categories')
                        ->label('Assign Categories')
                        ->icon('heroicon-o-folder')
                        ->color('info')
                        ->authorize(fn (): bool => FilamentPermission::hasAbility('product.update'))
                        ->form([
                            Forms\Components\Select::make('categories')
                                ->label('Categories')
                                ->relationship(
                                    'categories',
                                    'name',
                                    modifyQueryUsing: fn (Builder $query): Builder => self::scopeCategoriesQuery($query)
                                )
                                ->multiple()
                                ->searchable()
                                ->preload(),

                            Forms\Components\Radio::make('mode')
                                ->label('Mode')
                                ->options([
                                    'replace' => 'Replace existing categories',
                                    'add' => 'Add to existing categories',
                                ])
                                ->default('add')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $categories = $data['categories'] ?? [];
                            $categories = OwnerScopedIds::ensureAllowed('categories', Category::class, $categories);

                            foreach ($records as $product) {
                                if ($data['mode'] === 'replace') {
                                    $product->categories()->sync($categories);
                                } else {
                                    $product->categories()->syncWithoutDetaching($categories);
                                }
                            }

                            Notification::make()
                                ->title('Categories assigned')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    private static function resolveOwner(): ?Model
    {
        return OwnerContext::resolve();
    }

    private static function scopeCategoriesQuery(Builder $query): Builder
    {
        $owner = self::resolveOwner();

        return OwnerQuery::applyToEloquentBuilder($query->select(['id', 'name']), $owner, false);
    }

    /**
     * @return array{mode: string, minor: int, factor: float}
     */
    private static function priceAdjustments(string $action, mixed $value): array
    {
        $value = is_numeric($value) ? (float) $value : 0.0;
        $value = max(0.0, $value);

        return match ($action) {
            'set' => ['mode' => 'set', 'minor' => (int) round($value * 100), 'factor' => 1.0],
            'increase_percent' => ['mode' => 'percent', 'minor' => 0, 'factor' => (float) (1 + min($value, 100) / 100)],
            'decrease_percent' => ['mode' => 'percent', 'minor' => 0, 'factor' => (float) (1 - min($value, 100) / 100)],
            'increase_amount' => ['mode' => 'amount', 'minor' => (int) round($value * 100), 'factor' => 1.0],
            'decrease_amount' => ['mode' => 'amount', 'minor' => -1 * (int) round($value * 100), 'factor' => 1.0],
            default => ['mode' => 'percent', 'minor' => 0, 'factor' => 1.0],
        };
    }

    private static function duplicateProduct(Product $record): Product
    {
        return DB::transaction(static function () use ($record): Product {
            $ownerType = $record->getAttribute('owner_type');
            $ownerId = $record->getAttribute('owner_id');

            $newProduct = $record->replicate();
            $newProduct->name = $record->name . ' (Copy)';
            $newProduct->slug = self::uniqueOwnerValue(Product::class, 'slug', $record->slug . '-copy', $ownerType, $ownerId);
            $newProduct->sku = $record->sku !== null && $record->sku !== ''
                ? self::uniqueOwnerValue(Product::class, 'sku', $record->sku . '-COPY', $ownerType, $ownerId)
                : null;
            $newProduct->status = ProductStatus::Draft;
            $newProduct->save();

            $newProduct->categories()->sync($record->categories->pluck('id')->all());
            $newProduct->collections()->sync($record->collections->pluck('id')->all());
            $newProduct->tags()->sync($record->tags->pluck('id')->all());

            $valueMap = [];

            foreach ($record->options()->with('values')->get() as $option) {
                $newOption = $option->replicate();
                $newOption->product_id = $newProduct->getKey();
                $newOption->save();

                foreach ($option->values as $value) {
                    $newValue = $value->replicate();
                    $newValue->option_id = $newOption->getKey();
                    $newValue->save();
                    $valueMap[(string) $value->getKey()] = (string) $newValue->getKey();
                }
            }

            foreach ($record->variants()->with('optionValues')->get() as $variant) {
                $newVariant = $variant->replicate();
                $newVariant->product_id = $newProduct->getKey();
                $newVariant->sku = self::uniqueOwnerValue(Variant::class, 'sku', $variant->sku . '-COPY', $ownerType, $ownerId);
                $newVariant->save();

                $remapped = $variant->optionValues
                    ->map(static fn ($optionValue): ?string => $valueMap[(string) $optionValue->getKey()] ?? null)
                    ->filter()
                    ->all();

                $newVariant->optionValues()->sync($remapped);
            }

            if (class_exists(Price::class)) {
                foreach ($record->prices()->get() as $price) {
                    $newPrice = $price->replicate();
                    $newPrice->setAttribute('priceable_id', $newProduct->getKey());
                    $newPrice->save();
                }
            }

            return $newProduct;
        });
    }

    private static function duplicateProductMedia(Product $record, Product $newProduct): void
    {
        foreach ($record->getMedia() as $media) {
            $newProduct->copyMedia($media->getPath())->toMediaCollection($media->collection_name, $media->disk);
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function uniqueOwnerValue(string $modelClass, string $column, string $base, mixed $ownerType, mixed $ownerId): string
    {
        $candidate = $base;

        for ($suffix = 2; $suffix <= 100; $suffix++) {
            $query = $modelClass::query()->withoutGlobalScope(OwnerScope::class)->where($column, $candidate);

            if ($ownerType === null || $ownerId === null) {
                $query->whereNull('owner_type')->whereNull('owner_id');
            } else {
                $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
            }

            if (! $query->exists()) {
                return $candidate;
            }

            $candidate = $base . '-' . $suffix;
        }

        throw new Exception("Unable to generate a unique {$column} for the duplicated product.");
    }

    private static function importProducts(array $data): void
    {
        $csvFile = $data['csv_file'] ?? null;

        if (is_array($csvFile)) {
            $csvFile = $csvFile[0] ?? null;
        }

        try {
            if (! is_string($csvFile) || $csvFile === '') {
                throw new Exception('CSV file is missing.');
            }

            if (! Str::startsWith($csvFile, 'imports/')) {
                throw new Exception('Invalid CSV file path.');
            }

            if (! Storage::disk('local')->exists($csvFile)) {
                throw new Exception('CSV file not found.');
            }

            $filePath = Storage::disk('local')->path($csvFile);
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);

            $maxRows = max(1, (int) config('filament-products.import.max_rows', 1000));
            $rows = [];

            foreach ($csv->getRecords() as $offset => $record) {
                $rows[$offset] = $record;

                if (count($rows) > $maxRows) {
                    throw new Exception("The CSV file exceeds the {$maxRows} row import limit.");
                }
            }

            $updateExisting = (bool) ($data['update_existing'] ?? false);
            $skipErrors = (bool) ($data['skip_errors'] ?? true);

            $run = static function () use ($rows, $updateExisting, $skipErrors): array {
                $imported = 0;
                $updated = 0;
                $errors = [];

                foreach ($rows as $offset => $record) {
                    try {
                        $existing = null;

                        if ($updateExisting && isset($record['sku']) && mb_trim((string) $record['sku']) !== '') {
                            $existing = Product::query()->forOwner(self::resolveOwner(), false)->where('sku', mb_trim((string) $record['sku']))->first();
                        }

                        $productData = self::validateImportRow($record, $existing instanceof Product);

                        if ($existing instanceof Product) {
                            $existing->update($productData);
                            $updated++;

                            continue;
                        }

                        $product = new Product($productData);
                        $owner = self::resolveOwner();
                        if ($owner !== null) {
                            $product->assignOwner($owner);
                        }
                        $product->save();
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "Row {$offset}: {$e->getMessage()}";
                        if (! $skipErrors) {
                            throw $e;
                        }
                    }
                }

                return [$imported, $updated, $errors];
            };

            [$imported, $updated, $errors] = $skipErrors ? $run() : DB::transaction($run);

            Storage::disk('local')->delete((string) $csvFile);

            Notification::make()
                ->title('Import completed')
                ->body("Imported: {$imported}, Updated: {$updated}, Errors: " . count($errors))
                ->success()
                ->send();

            if (! empty($errors)) {
                Notification::make()
                    ->title('Import errors')
                    ->body(implode("\n", array_slice($errors, 0, 5)))
                    ->warning()
                    ->send();
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function validateImportRow(array $record, bool $isUpdate): array
    {
        $present = static fn (string $key): bool => isset($record[$key]) && mb_trim((string) $record[$key]) !== '';
        $text = static fn (string $key): string => mb_trim((string) ($record[$key] ?? ''));
        $data = [];

        if (! $isUpdate || $present('name')) {
            $name = $text('name');

            if ($name === '') {
                throw new Exception('The name field is required.');
            }

            if (mb_strlen($name) > 255) {
                throw new Exception('The name may not be longer than 255 characters.');
            }

            $data['name'] = $name;
        }

        if ($present('sku')) {
            if (mb_strlen($text('sku')) > 255) {
                throw new Exception('The sku may not be longer than 255 characters.');
            }

            $data['sku'] = $text('sku');
        } elseif (! $isUpdate && isset($record['sku'])) {
            $data['sku'] = null;
        }

        if (! $isUpdate || $present('slug')) {
            $slug = $present('slug') ? Str::slug($text('slug')) : Str::slug($text('name'));
            $slugMaxLength = (int) config('products.seo.slug_max_length', 100);

            if ($slug === '') {
                throw new Exception('The slug field is required.');
            }

            if (mb_strlen($slug) > $slugMaxLength) {
                throw new Exception("The slug may not be longer than {$slugMaxLength} characters.");
            }

            $data['slug'] = $slug;
        }

        foreach (['description', 'short_description', 'tax_class'] as $key) {
            if ($present($key)) {
                $value = $text($key);

                if ($key === 'tax_class' && mb_strlen($value) > 255) {
                    throw new Exception('The tax class may not be longer than 255 characters.');
                }

                $data[$key] = $value;
            }
        }

        if (! $isUpdate || $present('price')) {
            $data['price'] = self::importMinorAmount($record, 'price', true);
        }

        foreach (['compare_price', 'cost'] as $key) {
            if ($present($key)) {
                $data[$key] = self::importMinorAmount($record, $key, false);
            }
        }

        if ($present('weight')) {
            if (! is_numeric($text('weight'))) {
                throw new Exception('The weight must be numeric.');
            }

            $data['weight'] = $text('weight');
        }

        if ($present('currency')) {
            $currency = mb_strtoupper($text('currency'));

            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new Exception('The currency must be a three-letter code.');
            }

            $data['currency'] = $currency;
        }

        $enumDefaults = [
            'status' => ProductStatus::Draft,
            'type' => ProductType::Simple,
            'visibility' => ProductVisibility::CatalogSearch,
        ];

        foreach (['status' => ProductStatus::class, 'type' => ProductType::class, 'visibility' => ProductVisibility::class] as $key => $enum) {
            if ($present($key)) {
                $value = $enum::tryFrom($text($key));

                if ($value === null) {
                    throw new Exception("The {$key} value is invalid.");
                }

                $data[$key] = $value;
            } elseif (! $isUpdate) {
                $data[$key] = $enumDefaults[$key];
            }
        }

        foreach (['is_featured' => false, 'is_taxable' => true, 'requires_shipping' => true] as $key => $default) {
            if (array_key_exists($key, $record) && mb_trim((string) $record[$key]) !== '') {
                $data[$key] = filter_var($record[$key], FILTER_VALIDATE_BOOLEAN);
            } elseif (! $isUpdate) {
                $data[$key] = $default;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private static function importMinorAmount(array $record, string $key, bool $required): int
    {
        $raw = isset($record[$key]) ? mb_trim((string) $record[$key]) : '';

        if ($raw === '') {
            if ($required) {
                throw new Exception("The {$key} field is required.");
            }

            return 0;
        }

        if (! is_numeric($raw) || (float) $raw < 0) {
            throw new Exception("The {$key} must be a positive number.");
        }

        return (int) round(((float) $raw) * 100);
    }

    private static function exportProducts(array $data): StreamedResponse
    {
        $fields = array_values(array_filter(
            (array) ($data['fields'] ?? []),
            static fn (mixed $field): bool => is_string($field) && $field !== ''
        ));
        $statusFilter = $data['status_filter'] ?? 'all';

        return response()->streamDownload(function () use ($fields, $statusFilter): void {
            $query = Product::query()->forOwner();

            if ($statusFilter !== 'all') {
                $query->where('status', $statusFilter);
            }

            $csv = Writer::createFromPath('php://output', 'w');
            $csv->insertOne($fields);

            foreach ($query->cursor() as $product) {
                $row = [];
                foreach ($fields as $field) {
                    $value = $product->{$field};

                    if (in_array($field, ['price', 'compare_price', 'cost'], true) && is_numeric($value)) {
                        $value /= 100;
                    }

                    if ($value instanceof BackedEnum) {
                        $value = $value->value;
                    }

                    if (is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    }

                    $row[] = $value;
                }
                $csv->insertOne($row);
            }
        }, 'products-export-' . CarbonImmutable::now()->format('Y-m-d-His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private static function downloadTemplate(): StreamedResponse
    {
        $csv = Writer::createFromString();

        $csv->insertOne([
            'name',
            'sku',
            'slug',
            'description',
            'short_description',
            'currency',
            'price',
            'compare_price',
            'cost',
            'weight',
            'status',
            'type',
            'visibility',
            'is_featured',
            'is_taxable',
            'requires_shipping',
            'tax_class',
        ]);

        $csv->insertOne([
            'Example Product',
            'EXAMPLE-001',
            'example-product',
            'This is an example product description',
            'Short desc',
            'MYR',
            '99.99',
            '129.99',
            '50.00',
            '0.5',
            'active',
            'simple',
            'catalog_search',
            'true',
            'true',
            'true',
            'standard',
        ]);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv->toString();
        }, 'product-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
