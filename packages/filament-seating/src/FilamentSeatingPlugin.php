<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating;

use AIArmada\FilamentSeating\Pages\SeatMapEditor;
use AIArmada\FilamentSeating\Pages\SeatMapOccupancy;
use AIArmada\FilamentSeating\Resources\SeatMapResource;
use AIArmada\FilamentSeating\Widgets\SeatMapOverview;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentSeatingPlugin implements Plugin
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
        return 'filament-seating';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources($this->getResources())
            ->pages($this->getPages())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void {}

    private function getResources(): array
    {
        $e = config('filament-seating.resources.enabled', []);
        $r = [];

        if ($e['seat_map'] ?? true) {
            $r[] = SeatMapResource::class;
        }

        return $r;
    }

    private function getWidgets(): array
    {
        return [
            SeatMapOverview::class,
        ];
    }

    private function getPages(): array
    {
        return [
            SeatMapEditor::class,
            SeatMapOccupancy::class,
        ];
    }
}
