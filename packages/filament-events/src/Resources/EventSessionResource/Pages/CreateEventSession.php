<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSessionResource\Pages;

use AIArmada\Events\Features\Catalog\Actions\CreateEventSessionAction;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\FilamentEvents\Resources\EventSessionResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class CreateEventSession extends CreateRecord
{
    protected static string $resource = EventSessionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): EventSession
    {
        $occurrence = ModelResolver::occurrenceClass()::query()
            ->findOrFail($data['event_occurrence_id']);

        if (! $occurrence instanceof EventOccurrence) {
            throw new LogicException('Configured occurrence model must extend EventOccurrence.');
        }

        $event = EventWriteGuard::findOrFail($occurrence->event_id);
        Gate::forUser(Filament::auth()->user())->authorize('create', EventSessionResource::getModel());
        Gate::forUser(Filament::auth()->user())->authorize('update', $event);
        Gate::forUser(Filament::auth()->user())->authorize('update', $occurrence);

        return app(CreateEventSessionAction::class)->handle($occurrence, $data);
    }
}
