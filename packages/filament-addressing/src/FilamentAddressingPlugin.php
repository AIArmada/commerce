<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing;

use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use AIArmada\FilamentAddressing\Resources\AddressCityResource;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use AIArmada\FilamentAddressing\Resources\AddressSnapshotResource;
use AIArmada\FilamentAddressing\Resources\AddressStateResource;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentAddressingPlugin implements Plugin
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
        return 'filament-addressing';
    }

    public function register(Panel $panel): void
    {
        $panel->resources($this->getResources());
    }

    public function boot(Panel $panel): void {}

    private function getResources(): array
    {
        $resources = [];

        if (config('filament-addressing.resources.countries.enabled')) {
            $resources[] = AddressCountryResource::class;
        }

        if (config('filament-addressing.resources.states.enabled')) {
            $resources[] = AddressStateResource::class;
        }

        if (config('filament-addressing.resources.cities.enabled')) {
            $resources[] = AddressCityResource::class;
        }

        if (config('filament-addressing.resources.areas.enabled')) {
            $resources[] = AddressAreaResource::class;
        }

        if (config('filament-addressing.resources.postal_codes.enabled')) {
            $resources[] = PostalCodeResource::class;
        }

        if (config('filament-addressing.resources.addresses.enabled')) {
            $resources[] = AddressResource::class;
        }

        if (config('filament-addressing.resources.snapshots.enabled')) {
            $resources[] = AddressSnapshotResource::class;
        }

        return $resources;
    }
}
