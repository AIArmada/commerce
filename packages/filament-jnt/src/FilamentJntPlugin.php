<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt;

use AIArmada\FilamentJnt\Resources\JntOrderResource;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentJntPlugin implements Plugin
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
        return 'filament-jnt';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            JntOrderResource::class,
            JntTrackingEventResource::class,
            JntWebhookLogResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
