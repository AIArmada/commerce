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
            ->resources($this->getResources())
            ->pages($this->getPages())
            ->widgets([
                Widgets\ProductStatsWidget::class,
                Widgets\CategoryDistributionChart::class,
                Widgets\ProductTypeDistributionWidget::class,
                Widgets\TopSellingProductsWidget::class,
            ]);
    }

    /**
     * @return array<class-string>
     */
    private function getResources(): array
    {
        $resources = [
            Resources\ProductResource::class,
            Resources\CategoryResource::class,
            Resources\AttributeGroupResource::class,
            Resources\AttributeSetResource::class,
        ];

        if (config('filament-products.features.collections', true)) {
            $resources[] = Resources\CollectionResource::class;
        }

        if (config('filament-products.features.attributes', true)) {
            $resources[] = Resources\AttributeResource::class;
        }

        return $resources;
    }

    /**
     * @return array<class-string>
     */
    private function getPages(): array
    {
        $pages = [];

        if (config('filament-products.features.bulk_edit', true)) {
            $pages[] = Pages\BulkEditProducts::class;
        }

        if (config('filament-products.features.import_export', true)) {
            $pages[] = Pages\ImportExportProducts::class;
        }

        return $pages;
    }

    public function boot(Panel $panel): void
    {
        // Boot logic here
    }
}
