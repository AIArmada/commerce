<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSessionResource\Pages;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Actions\CreateEventSessionAction;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\FilamentEvents\Resources\EventSessionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEventSession extends CreateRecord
{
    protected static string $resource = EventSessionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): EventSession
    {
        $occurrence = EventOccurrence::query()
            ->with('event')
            ->findOrFail($data['event_occurrence_id']);

        $this->resolveEventForWrite($occurrence->event_id);

        return app(CreateEventSessionAction::class)->handle($occurrence, $data);
    }

    private function resolveEventForWrite(int | string $eventId): Event
    {
        if (method_exists(Event::class, 'ownerScopeConfig') && ! Event::ownerScopeConfig()->enabled) {
            return Event::query()->findOrFail($eventId);
        }

        return OwnerWriteGuard::findOrFailForOwner(Event::class, $eventId);
    }
}
