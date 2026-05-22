<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class TopSellingProductsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Products';

    /**
     * @return Builder<Product>
     */
    protected function getRecentProductsQuery(): Builder
    {
        /** @var Builder<Product> $query */
        $query = $this->withResolvedOwnerOrExplicitGlobal(function (): Builder {
            return Product::query()
                ->forOwner()
                ->where('status', ProductStatus::Active)
                ->latest()
                ->limit(10);
        });

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->getRecentProductsQuery()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->sku),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color('info'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money(fn (Product $record): string => $record->currency, divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\TextColumn::make('variants_count')
                    ->label('Variants')
                    ->getStateUsing(fn (Product $record): int => $this->getVariantsCount($record)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->sortable(),
            ])
            ->description('Recently added products in the catalog');
    }

    private function withResolvedOwnerOrExplicitGlobal(callable $callback): mixed
    {
        if (OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal()) {
            return $callback();
        }

        return OwnerContext::withOwner(null, static fn (): mixed => $callback());
    }

    private function getVariantsCount(Product $record): int
    {
        if (! method_exists($record, 'variants')) {
            return 0;
        }

        /** @var int $count */
        $count = $this->withResolvedOwnerOrExplicitGlobal(static fn (): int => $record->variants()->count());

        return $count;
    }
}
