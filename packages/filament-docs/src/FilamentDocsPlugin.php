<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs;

use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentDocsPlugin implements Plugin
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
        return 'filament-docs';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                DocResource::class,
                DocTemplateResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
