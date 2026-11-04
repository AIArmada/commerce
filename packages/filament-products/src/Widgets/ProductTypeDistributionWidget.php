<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Displays product distribution by type (Physical, Digital, Subscription).
 */
final class ProductTypeDistributionWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return $this->withResolvedOwnerOrExplicitGlobal(function (): array {
            $counts = Product::query()->forOwner()
                ->toBase()
                ->selectRaw(
                    'SUM(CASE WHEN status = ? AND type IN (?, ?, ?) AND requires_shipping = ? THEN 1 ELSE 0 END) AS physical_products',
                    [
                        ProductStatus::Active->value,
                        ProductType::Simple->value,
                        ProductType::Configurable->value,
                        ProductType::Bundle->value,
                        true,
                    ],
                )
                ->selectRaw(
                    'SUM(CASE WHEN status = ? AND type = ? THEN 1 ELSE 0 END) AS digital_products',
                    [ProductStatus::Active->value, ProductType::Digital->value],
                )
                ->selectRaw(
                    'SUM(CASE WHEN status = ? AND type = ? THEN 1 ELSE 0 END) AS subscription_products',
                    [ProductStatus::Active->value, ProductType::Subscription->value],
                )
                ->selectRaw(
                    'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS total_active',
                    [ProductStatus::Active->value],
                )
                ->first();

            $counts = (array) ($counts ?? []);

            $physicalProducts = (int) ($counts['physical_products'] ?? 0);
            $digitalProducts = (int) ($counts['digital_products'] ?? 0);
            $subscriptionProducts = (int) ($counts['subscription_products'] ?? 0);
            $totalActive = (int) ($counts['total_active'] ?? 0);

            return [
                Stat::make('Physical Products', $physicalProducts)
                    ->description('Require shipping')
                    ->descriptionIcon('heroicon-o-truck')
                    ->color('info'),

                Stat::make('Digital Products', $digitalProducts)
                    ->description('Downloadable')
                    ->descriptionIcon('heroicon-o-cloud-arrow-down')
                    ->color('success'),

                Stat::make('Subscriptions', $subscriptionProducts)
                    ->description('Recurring billing')
                    ->descriptionIcon('heroicon-o-arrow-path')
                    ->color('primary'),

                Stat::make('Total Active', $totalActive)
                    ->description('Active in catalog')
                    ->descriptionIcon('heroicon-o-check-circle')
                    ->color('success'),
            ];
        });
    }

    private function withResolvedOwnerOrExplicitGlobal(callable $callback): mixed
    {
        OwnerContext::assertResolvedOrExplicitGlobal(
            OwnerContext::resolve(),
            'Product type distribution requires an owner context or explicit global context.',
        );

        return $callback();
    }
}
