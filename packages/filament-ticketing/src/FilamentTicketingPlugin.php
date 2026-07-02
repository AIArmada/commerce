<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing;

use AIArmada\FilamentTicketing\Resources\PassHolderResource;
use AIArmada\FilamentTicketing\Resources\PassResource;
use AIArmada\FilamentTicketing\Resources\PassTransferResource;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentTicketingPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        $plugin = filament(app(self::class)->getId());

        assert($plugin instanceof static);

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-ticketing';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources($this->getResources())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void {}

    private function getResources(): array
    {
        $e = config('filament-ticketing.resources.enabled', []);
        $r = [];

        if ($e['ticket_type'] ?? true) {
            $r[] = TicketTypeResource::class;
        }
        if ($e['pass'] ?? true) {
            $r[] = PassResource::class;
        }
        if ($e['pass_holder'] ?? true) {
            $r[] = PassHolderResource::class;
        }
        if ($e['pass_transfer'] ?? true) {
            $r[] = PassTransferResource::class;
        }

        return $r;
    }

    private function getWidgets(): array
    {
        return [];
    }
}
