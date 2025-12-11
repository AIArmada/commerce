<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Pages;

use AIArmada\Products\Models\Product;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class BulkEditProducts extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected string $view = 'filament-products::pages.bulk-edit-products';

    protected static ?string $navigationGroup = 'Products';

    protected static ?int $navigationSort = 98;

    protected static ?string $title = 'Bulk Edit';

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query())
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('media')
                    ->label('')
                    ->collection('hero')
                    ->conversion('thumb')
                    ->circular()
                    ->width(50)
                    ->height(50),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('sku')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('price')
                    ->money('MYR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state <= 0 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'draft' => 'warning',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'draft' => 'Draft',
                        'archived' => 'Archived',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'simple' => 'Simple',
                        'variable' => 'Variable',
                        'digital' => 'Digital',
                    ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('update_price')
                        ->label('Update Price')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('success')
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
                                ->label(function (Forms\Get $get) {
                                    return match ($get('price_action')) {
                                        'set' => 'New Price (RM)',
                                        'increase_percent', 'decrease_percent' => 'Percentage (%)',
                                        'increase_amount', 'decrease_amount' => 'Amount (RM)',
                                        default => 'Value',
                                    };
                                })
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $product) {
                                $currentPrice = $product->price / 100;

                                $newPrice = match ($data['price_action']) {
                                    'set' => $data['value'],
                                    'increase_percent' => $currentPrice * (1 + $data['value'] / 100),
                                    'decrease_percent' => $currentPrice * (1 - $data['value'] / 100),
                                    'increase_amount' => $currentPrice + $data['value'],
                                    'decrease_amount' => $currentPrice - $data['value'],
                                    default => $currentPrice,
                                };

                                $product->update(['price' => (int) ($newPrice * 100)]);
                            }

                            Notification::make()
                                ->title('Prices updated')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('update_stock')
                        ->label('Update Stock')
                        ->icon('heroicon-o-cube')
                        ->color('warning')
                        ->form([
                            Forms\Components\Radio::make('stock_action')
                                ->label('Action')
                                ->options([
                                    'set' => 'Set to specific quantity',
                                    'increase' => 'Increase quantity',
                                    'decrease' => 'Decrease quantity',
                                ])
                                ->required()
                                ->live()
                                ->default('set'),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $product) {
                                $currentStock = $product->stock_quantity;

                                $newStock = match ($data['stock_action']) {
                                    'set' => $data['quantity'],
                                    'increase' => $currentStock + $data['quantity'],
                                    'decrease' => max(0, $currentStock - $data['quantity']),
                                    default => $currentStock,
                                };

                                $product->update(['stock_quantity' => $newStock]);
                            }

                            Notification::make()
                                ->title('Stock updated')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('update_status')
                        ->label('Change Status')
                        ->icon('heroicon-o-flag')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('New Status')
                                ->options([
                                    'active' => 'Active',
                                    'draft' => 'Draft',
                                    'archived' => 'Archived',
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $product) {
                                $product->update(['status' => $data['status']]);
                            }

                            Notification::make()
                                ->title('Status updated')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('assign_categories')
                        ->label('Assign Categories')
                        ->icon('heroicon-o-folder')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('categories')
                                ->label('Categories')
                                ->relationship('categories', 'name')
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
                        ->action(function ($records, array $data): void {
                            foreach ($records as $product) {
                                if ($data['mode'] === 'replace') {
                                    $product->categories()->sync($data['categories']);
                                } else {
                                    $product->categories()->syncWithoutDetaching($data['categories']);
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
}
