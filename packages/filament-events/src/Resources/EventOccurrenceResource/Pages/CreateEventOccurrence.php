<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventOccurrenceResource\Pages;

use AIArmada\Events\Features\Catalog\Actions\CreateEventOccurrenceAction;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class CreateEventOccurrence extends CreateRecord
{
    protected static string $resource = EventOccurrenceResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): EventOccurrence
    {
        $event = EventWriteGuard::findOrFail($data['event_id']);

        if (! $event instanceof Event) {
            throw new LogicException('Configured event model must extend Event.');
        }

        Gate::forUser(Filament::auth()->user())->authorize('create', EventOccurrenceResource::getModel());
        Gate::forUser(Filament::auth()->user())->authorize('update', $event);

        return app(CreateEventOccurrenceAction::class)->handle($event, $data);
    }

}
