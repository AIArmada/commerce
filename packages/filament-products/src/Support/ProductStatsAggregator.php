<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;

final class ProductStatsAggregator
{
    public function getStats(): array
    {
        return $this->withResolvedOwnerOrExplicitGlobal(function (): array {
            $totalProducts = Product::query()->forOwner()->count();
            $activeProducts = Product::query()->forOwner()->where('status', ProductStatus::Active)->count();
            $draftProducts = Product::query()->forOwner()->where('status', ProductStatus::Draft)->count();
            $totalCategories = Category::query()->forOwner()->count();
            $totalCollections = Collection::query()->forOwner()->where('is_visible', true)->count();

            $lastWeekProducts = Product::query()->forOwner()->where('created_at', '>=', now()->subWeek())->count();
            $previousWeekProducts = Product::query()->forOwner()
                ->whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])
                ->count();

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
        });
    }

    private function withResolvedOwnerOrExplicitGlobal(callable $callback): mixed
    {
        if (OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal()) {
            return $callback();
        }

        return OwnerContext::withOwner(null, static fn (): mixed => $callback());
    }
}
