<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations;

use Filament\Contracts\Plugin;
use Filament\Panel;
use LogicException;

final class FilamentOrganizationsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        $plugin = filament(app(self::class)->getId());

        if (! $plugin instanceof static) {
            throw new LogicException('The Filament Organizations plugin is not registered.');
        }

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-organizations';
    }

    public function register(Panel $panel): void
    {
        if ((bool) config('filament-organizations.resources.enabled', true)) {
            $panel->resources([
                Resources\OrganizationResource::class,
            ]);
        }
    }

    public function boot(Panel $panel): void {}
}
