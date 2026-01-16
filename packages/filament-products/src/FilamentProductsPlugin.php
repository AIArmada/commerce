<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentProductsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-products';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\ProductResource::class,
                Resources\CategoryResource::class,
                Resources\CollectionResource::class,
                Resources\AttributeResource::class,
                Resources\AttributeGroupResource::class,
                Resources\AttributeSetResource::class,
            ])
            ->pages([
                Pages\BulkEditProducts::class,
                Pages\ImportExportProducts::class,
            ])
            ->widgets([
                Widgets\ProductStatsWidget::class,
                Widgets\CategoryDistributionChart::class,
                Widgets\ProductTypeDistributionWidget::class,
                Widgets\TopSellingProductsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // Boot logic here
    }
}
