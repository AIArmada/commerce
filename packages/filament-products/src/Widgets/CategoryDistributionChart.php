<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Widgets;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Products\Models\Category;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CategoryDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Products by Category';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $pivotTable = config('products.tables.category_product', 'category_product');
        $categoriesTable = (new Category)->getTable();
        $productsTable = (new \AIArmada\Products\Models\Product)->getTable();

        $query = Category::query();

        if ((bool) config('products.owner.enabled', true)) {
            $owner = null;

            if (app()->bound(OwnerResolverInterface::class)) {
                $owner = app(OwnerResolverInterface::class)->resolve();
            }

            if ($owner instanceof Model) {
                $includeGlobal = (bool) config('products.owner.include_global', true);

                $query->where(function (Builder $builder) use ($categoriesTable, $owner, $includeGlobal): void {
                    $builder->where($categoriesTable . '.owner_type', $owner->getMorphClass())
                        ->where($categoriesTable . '.owner_id', $owner->getKey());

                    if ($includeGlobal) {
                        $builder->orWhere(function (Builder $inner) use ($categoriesTable): void {
                            $inner->whereNull($categoriesTable . '.owner_type')
                                ->whereNull($categoriesTable . '.owner_id');
                        });
                    }
                });
            } else {
                $query->whereNull($categoriesTable . '.owner_type')
                    ->whereNull($categoriesTable . '.owner_id');
            }
        }

        $categories = $query
            ->select([
                $categoriesTable . '.id',
                $categoriesTable . '.name',
            ])
            ->join($pivotTable, $categoriesTable . '.id', '=', $pivotTable . '.category_id')
            ->join($productsTable, $productsTable . '.id', '=', $pivotTable . '.product_id')
            ->whereIn($productsTable . '.id', \AIArmada\Products\Models\Product::query()->forOwner()->select('id'))
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
