<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Widgets;

use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use Filament\Widgets\ChartWidget;

final class CategoryDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Products by Category';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $pivotTable = config('products.database.tables.category_product', 'category_product');
        $categoriesTable = (new Category)->getTable();
        $productsTable = (new Product)->getTable();

        $productsSubquery = Product::query()
            ->forOwner()
            ->select('id');

        $categories = Category::query()
            ->forOwner()
            ->select([
                $categoriesTable . '.id',
                $categoriesTable . '.name',
            ])
            ->join($pivotTable, $categoriesTable . '.id', '=', $pivotTable . '.category_id')
            ->join($productsTable, $productsTable . '.id', '=', $pivotTable . '.product_id')
            ->whereIn($productsTable . '.id', $productsSubquery)
            ->selectRaw('count(' . $productsTable . '.id) as products_count')
            ->groupBy($categoriesTable . '.id', $categoriesTable . '.name')
            ->orderByDesc('products_count')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Products',
                    'data' => $categories->pluck('products_count')->toArray(),
                    'backgroundColor' => [
                        '#3b82f6',
                        '#8b5cf6',
                        '#ec4899',
                        '#f59e0b',
                        '#10b981',
                        '#06b6d4',
                        '#6366f1',
                        '#f97316',
                        '#14b8a6',
                        '#a855f7',
                    ],
                ],
            ],
            'labels' => $categories->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
