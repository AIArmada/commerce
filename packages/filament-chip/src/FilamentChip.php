<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip;

use AIArmada\FilamentChip\Resources\ClientResource;
use AIArmada\FilamentChip\Resources\PaymentResource;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentChip implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-chip';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            PurchaseResource::class,
            PaymentResource::class,
            ClientResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
