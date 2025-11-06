<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart;

use AIArmada\FilamentCart\Resources\CartConditionResource;
use AIArmada\FilamentCart\Resources\CartItemResource;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Resources\ConditionResource;
use AIArmada\FilamentCart\Widgets\CartStatsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentCart implements Plugin
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
        return 'filament-cart';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                CartResource::class,
                CartItemResource::class,
                CartConditionResource::class,
                ConditionResource::class,
            ])
            ->widgets([
                CartStatsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
