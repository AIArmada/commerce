<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventOccurrenceResource\Pages;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Actions\CreateEventOccurrenceAction;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEventOccurrence extends CreateRecord
{
    protected static string $resource = EventOccurrenceResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): EventOccurrence
    {
        $event = $this->resolveEventForWrite($data['event_id']);

        return app(CreateEventOccurrenceAction::class)->handle($event, $data);
    }

    private function resolveEventForWrite(int | string $eventId): Event
    {
        if (method_exists(Event::class, 'ownerScopeConfig') && ! Event::ownerScopeConfig()->enabled) {
            return Event::query()->findOrFail($eventId);
        }

        return OwnerWriteGuard::findOrFailForOwner(Event::class, $eventId);
    }
}
