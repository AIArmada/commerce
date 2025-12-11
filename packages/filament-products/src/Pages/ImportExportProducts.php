<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Pages;

use AIArmada\Products\Models\Product;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Writer;
use UnitEnum;

class ImportExportProducts extends Page
{
    public ?array $importData = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected string $view = 'filament-products::pages.import-export-products';

    protected static string | UnitEnum | null $navigationGroup = 'Products';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Import / Export';

    public function importForm(): Forms\Form
    {
        return Forms\Form::make()
            ->schema([
                Forms\Components\Section::make('Import Products')
                    ->schema([
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'text/plain'])
                            ->required()
                            ->disk('local')
                            ->directory('imports')
                            ->helperText('Upload a CSV file with product data'),

                        Forms\Components\Toggle::make('update_existing')
                            ->label('Update Existing Products')
                            ->helperText('Update products that already exist (matched by SKU)')
                            ->default(false),

                        Forms\Components\Toggle::make('skip_errors')
                            ->label('Skip Errors')
                            ->helperText('Continue importing even if some rows have errors')
                            ->default(true),
                    ]),
            ])
            ->statePath('importData');
    }

    public function import(): void
    {
        $data = $this->importForm()->getState();

        try {
            $filePath = Storage::disk('local')->path($data['csv_file']);
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);

            $records = $csv->getRecords();
            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($records as $offset => $record) {
                try {
                    $productData = [
                        'name' => $record['name'] ?? null,
                        'sku' => $record['sku'] ?? null,
                        'slug' => $record['slug'] ?? \Illuminate\Support\Str::slug($record['name'] ?? ''),
                        'description' => $record['description'] ?? null,
                        'price' => isset($record['price']) ? (int) ($record['price'] * 100) : 0,
                        'compare_at_price' => isset($record['compare_at_price']) ? (int) ($record['compare_at_price'] * 100) : null,
                        'cost' => isset($record['cost']) ? (int) ($record['cost'] * 100) : null,
                        'stock_quantity' => $record['stock_quantity'] ?? 0,
                        'low_stock_threshold' => $record['low_stock_threshold'] ?? 5,
                        'weight' => $record['weight'] ?? null,
                        'status' => $record['status'] ?? 'draft',
                        'type' => $record['type'] ?? 'simple',
                        'visibility' => $record['visibility'] ?? 'visible',
                    ];

                    if ($data['update_existing'] && isset($record['sku'])) {
                        $product = Product::where('sku', $record['sku'])->first();
                        if ($product) {
                            $product->update($productData);
                            $updated++;

                            continue;
                        }
                    }

                    Product::create($productData);
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "Row {$offset}: {$e->getMessage()}";
                    if (! $data['skip_errors']) {
                        throw $e;
                    }
                }
            }

            // Clean up uploaded file
            Storage::disk('local')->delete($data['csv_file']);

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

            $this->importData = [];
        } catch (Exception $e) {
            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export to CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Forms\Components\CheckboxList::make('fields')
                        ->label('Select Fields to Export')
                        ->options([
                            'name' => 'Name',
                            'sku' => 'SKU',
                            'slug' => 'Slug',
                            'description' => 'Description',
                            'price' => 'Price',
                            'compare_at_price' => 'Compare at Price',
                            'cost' => 'Cost',
                            'stock_quantity' => 'Stock Quantity',
                            'low_stock_threshold' => 'Low Stock Threshold',
                            'weight' => 'Weight',
                            'status' => 'Status',
                            'type' => 'Type',
                            'visibility' => 'Visibility',
                        ])
                        ->default(['name', 'sku', 'price', 'stock_quantity'])
                        ->required()
                        ->columns(3),

                    Forms\Components\Select::make('status_filter')
                        ->label('Filter by Status')
                        ->options([
                            'all' => 'All Products',
                            'active' => 'Active Only',
                            'draft' => 'Draft Only',
                            'archived' => 'Archived Only',
                        ])
                        ->default('all'),
                ])
                ->action(function (array $data) {
                    return $this->exportProducts($data);
                }),

            Action::make('download_template')
                ->label('Download CSV Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    return $this->downloadTemplate();
                }),
        ];
    }

    protected function exportProducts(array $data): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = Product::query();

        // Apply status filter
        if ($data['status_filter'] !== 'all') {
            $query->where('status', $data['status_filter']);
        }

        $products = $query->get();

        $csv = Writer::createFromString();

        // Add headers
        $csv->insertOne($data['fields']);

        // Add data
        foreach ($products as $product) {
            $row = [];
            foreach ($data['fields'] as $field) {
                $value = $product->{$field};

                // Convert cents to dollars for price fields
                if (in_array($field, ['price', 'compare_at_price', 'cost']) && is_numeric($value)) {
                    $value /= 100;
                }

                $row[] = $value;
            }
            $csv->insertOne($row);
        }

        return response()->streamDownload(function () use ($csv): void {
            echo $csv->toString();
        }, 'products-export-' . now()->format('Y-m-d-His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $csv = Writer::createFromString();

        $csv->insertOne([
            'name',
            'sku',
            'slug',
            'description',
            'price',
            'compare_at_price',
            'cost',
            'stock_quantity',
            'low_stock_threshold',
            'weight',
            'status',
            'type',
            'visibility',
        ]);

        // Add example row
        $csv->insertOne([
            'Example Product',
            'EXAMPLE-001',
            'example-product',
            'This is an example product description',
            '99.99',
            '129.99',
            '50.00',
            '100',
            '10',
            '500',
            'active',
            'simple',
            'visible',
        ]);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv->toString();
        }, 'product-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
