<?php

declare(strict_types=1);

namespace AIArmada\Products\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use Carbon\CarbonImmutable;

final readonly class CatalogStatistics
{
    /** @return array<string, int|float> */
    public function execute(): array
    {
        OwnerContext::assertResolvedOrExplicitGlobal(
            OwnerContext::resolve(),
            'Product statistics require an owner context or explicit global context.',
        );

        $now = CarbonImmutable::now();
        $lastWeekStart = $now->subWeek();
        $previousWeekStart = $now->subWeeks(2);

        $productStats = Product::query()->forOwner()
            ->toBase()
            ->selectRaw('COUNT(*) AS total_products')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS active_products', [ProductStatus::Active->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft_products', [ProductStatus::Draft->value])
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) AS last_week_products', [$lastWeekStart, $now])
            ->selectRaw(
                'SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS previous_week_products',
                [$previousWeekStart, $lastWeekStart],
            )
            ->first();

        $productStats = (array) ($productStats ?? []);

        $totalProducts = (int) ($productStats['total_products'] ?? 0);
        $activeProducts = (int) ($productStats['active_products'] ?? 0);
        $draftProducts = (int) ($productStats['draft_products'] ?? 0);
        $lastWeekProducts = (int) ($productStats['last_week_products'] ?? 0);
        $previousWeekProducts = (int) ($productStats['previous_week_products'] ?? 0);

        $totalCategories = Category::query()->forOwner()->count();
        $totalCollections = Collection::query()->forOwner()->visible()->count();

        $trend = $previousWeekProducts > 0
            ? round((($lastWeekProducts - $previousWeekProducts) / $previousWeekProducts) * 100)
            : ($lastWeekProducts > 0 ? 100 : 0);

        return [
            'totalProducts' => $totalProducts,
            'activeProducts' => $activeProducts,
            'draftProducts' => $draftProducts,
            'totalCategories' => $totalCategories,
            'totalCollections' => $totalCollections,
            'lastWeekProducts' => $lastWeekProducts,
            'previousWeekProducts' => $previousWeekProducts,
            'trend' => $trend,
        ];
    }
}
