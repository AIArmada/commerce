<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentOrganizationsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /* @phpstan-ignore return.type */
        return filament(app(self::class)->getId());
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
