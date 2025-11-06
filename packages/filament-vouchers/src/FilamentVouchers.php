<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers;

use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource;
use AIArmada\FilamentVouchers\Widgets\VoucherStatsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentVouchers implements Plugin
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
        return 'filament-vouchers';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                VoucherResource::class,
                VoucherUsageResource::class,
            ])
            ->widgets([
                VoucherStatsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // No-op: the service provider handles runtime integration hooks.
    }
}
