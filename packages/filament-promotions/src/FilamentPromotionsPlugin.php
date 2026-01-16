<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions;

use AIArmada\FilamentPromotions\Resources\PromotionResource;
use AIArmada\FilamentPromotions\Widgets\PromotionStatsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentPromotionsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-promotions';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                PromotionResource::class,
            ])
            ->widgets([
                PromotionStatsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // No-op: the service provider handles runtime integration hooks.
    }
}
