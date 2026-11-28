<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventOccurrenceResource\Pages;

use AIArmada\Events\Features\Catalog\Actions\UpdateEventOccurrenceAction;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class EditEventOccurrence extends EditRecord
{
    protected static string $resource = EventOccurrenceResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof EventOccurrence) {
            throw new LogicException('Unexpected occurrence record.');
        }

        Gate::forUser(Filament::auth()->user())->authorize('update', $record);

        return app(UpdateEventOccurrenceAction::class)->handle($record, $data)['occurrence'];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
