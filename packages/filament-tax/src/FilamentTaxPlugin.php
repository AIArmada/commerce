<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentTaxPlugin implements Plugin
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
        return 'filament-tax';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\TaxZoneResource::class,
                Resources\TaxClassResource::class,
            ])
            ->widgets([
                Widgets\TaxStatsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
