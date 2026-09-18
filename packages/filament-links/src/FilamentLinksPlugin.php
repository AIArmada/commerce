<?php

declare(strict_types=1);

namespace AIArmada\FilamentLinks;

use AIArmada\FilamentLinks\Resources\LinkResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentLinksPlugin implements Plugin
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
        return 'filament-links';
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if (config('filament-links.features.links', true)) {
            $resources[] = LinkResource::class;
        }

        $panel->resources($resources);
    }

    public function boot(Panel $panel): void {}
}
