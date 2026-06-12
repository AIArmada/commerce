<?php
declare(strict_types=1);
namespace AIArmada\FilamentEvents;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentEventsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        return filament(app(self::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-events';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources($this->getResources())
            ->pages($this->getPages())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void {}

    private function getPages(): array
    {
        return [
            Pages\CheckInConsole::class,
            Pages\NotificationCenter::class,
            Pages\ApprovalQueue::class,
            Pages\EventPublicPreview::class,
            Pages\SeatMapManager::class,
        ];
    }

    private function getResources(): array
    {
        $e = config('filament-events.resources.enabled', []);
        $r = [];

        if ($e['event'] ?? true) $r[] = Resources\EventResource::class;
        if ($e['occurrence'] ?? true) $r[] = Resources\EventOccurrenceResource::class;
        if ($e['session'] ?? true) $r[] = Resources\EventSessionResource::class;
        if ($e['venue'] ?? true) $r[] = Resources\VenueResource::class;
        if ($e['registration'] ?? true) $r[] = Resources\EventRegistrationResource::class;
        if ($e['ticket_type'] ?? true) $r[] = Resources\EventTicketTypeResource::class;
        if ($e['attendance'] ?? true) $r[] = Resources\EventAttendanceResource::class;
        if ($e['change_log'] ?? true) $r[] = Resources\EventChangeLogResource::class;

        return $r;
    }

    private function getWidgets(): array
    {
        return [
            Widgets\EventStatsWidget::class,
        ];
    }
}
