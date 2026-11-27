<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Tables;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\Pricing\Models\Price;
use AIArmada\Products\Actions\DuplicateProduct;
use AIArmada\Products\Actions\ImportCatalogProducts;
use AIArmada\Products\Actions\UpdateCatalogProducts;
use AIArmada\Products\Actions\UpdateProductStatus;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Queries\CatalogExport;
use Carbon\CarbonImmutable;
use Closure;
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
use Illuminate\Support\Facades\Storage;
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
                        ProductType::Composite => 'warning',
                        ProductType::Digital => 'success',
                        ProductType::Service => 'info',
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
                        self::importProducts($data, static fn (?Product $product): bool => $product === null
                            ? FilamentPermission::hasAbility('product.create')
                            : FilamentPermission::hasAbility('product.update') && (auth()->user()?->can('update', $product) ?? false));
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
                    ->searchable()
                    ->native(false),
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
                            $newProduct = app(DuplicateProduct::class)->execute($record);
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
                        ->authorizeIndividualRecords('update')
                        ->action(function (Collection $records): void {
                            $records->each(function (Product $record): void {
                                app(UpdateProductStatus::class)->execute($record, ProductStatus::Active);
                            });
                        }),
                    BulkAction::make('draft')
                        ->label('Set to Draft')
                        ->icon('heroicon-o-pencil')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => auth()->user()?->can('updateAny', Product::class) ?? false)
                        ->authorizeIndividualRecords('update')
                        ->action(function (Collection $records): void {
                            $records->each(function (Product $record): void {
                                app(UpdateProductStatus::class)->execute($record, ProductStatus::Draft);
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
                        ->authorizeIndividualRecords('update')
                        ->action(function (Collection $records, array $data): void {
                            app(UpdateCatalogProducts::class)->adjustPrices($records, $data['price_action'] ?? '', $data['value'] ?? null);

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
                        ->authorizeIndividualRecords('update')
                        ->action(function (Collection $records, array $data): void {
                            app(UpdateCatalogProducts::class)->setVisibility($records, ProductVisibility::from($data['visibility']));

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
                        ->authorizeIndividualRecords('update')
                        ->action(function (Collection $records, array $data): void {
                            app(UpdateCatalogProducts::class)->assignCategories($records, $data['categories'] ?? [], $data['mode'] === 'add');

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

    private static function duplicateProductMedia(Product $record, Product $newProduct): void
    {
        foreach ($record->getMedia() as $media) {
            $newProduct->copyMedia($media->getPath())->toMediaCollection($media->collection_name, $media->disk);
        }
    }

    private static function importProducts(array $data, Closure $authorize): void
    {
        $csvFile = $data['csv_file'] ?? null;

        if (is_array($csvFile)) {
            $csvFile = $csvFile[0] ?? null;
        }

        try {
            if (! is_string($csvFile) || $csvFile === '') {
                throw new Exception('CSV file is missing.');
            }

            if (! preg_match('~^imports/[a-zA-Z0-9_-]+\\.csv$~i', $csvFile)) {
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

            [$imported, $updated, $errors] = app(ImportCatalogProducts::class)->execute($rows, $updateExisting, $skipErrors, $authorize);

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

    private static function exportProducts(array $data): StreamedResponse
    {
        $fields = array_values((array) ($data['fields'] ?? []));
        $rows = app(CatalogExport::class)->rows($fields, (string) ($data['status_filter'] ?? 'all'));

        return response()->streamDownload(function () use ($fields, $rows): void {
            $csv = Writer::createFromPath('php://output', 'w');
            $csv->insertOne($fields);

            foreach ($rows as $row) {
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
