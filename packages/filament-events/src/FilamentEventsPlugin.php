<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentEventsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-events';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\EventSeriesResource::class,
                Resources\EventResource::class,
                Resources\OccurrenceResource::class,
                Resources\VenueResource::class,
                Resources\EventSubLocationResource::class,
                Resources\RegistrationResource::class,
            ]);
    }

    public function boot(Panel $panel): void {}
}
