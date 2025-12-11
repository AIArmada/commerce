<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentPricingPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-pricing';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\PriceListResource::class,
                Resources\PromotionResource::class,
            ])
            ->widgets([
                Widgets\PricingStatsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
