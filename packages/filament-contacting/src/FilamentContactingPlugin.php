<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting;

use AIArmada\FilamentContacting\Resources\ContactMethodResource;
use AIArmada\FilamentContacting\Resources\ContactSnapshotResource;
use AIArmada\FilamentContacting\Resources\SocialProfileResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentContactingPlugin implements Plugin
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
        return 'filament-contacting';
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if (config('filament-contacting.resources.contact_methods.enabled', false)) {
            $resources[] = ContactMethodResource::class;
        }

        if (config('filament-contacting.resources.social_profiles.enabled', false)) {
            $resources[] = SocialProfileResource::class;
        }

        if (config('filament-contacting.resources.contact_snapshots.enabled', false)) {
            $resources[] = ContactSnapshotResource::class;
        }

        if ($resources !== []) {
            $panel->resources($resources);
        }
    }

    public function boot(Panel $panel): void {}
}
