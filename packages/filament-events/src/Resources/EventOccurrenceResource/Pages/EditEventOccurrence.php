<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventOccurrenceResource\Pages;

use AIArmada\Events\Features\Catalog\Actions\ArchiveScheduleRecordAction;
use AIArmada\Events\Features\Catalog\Actions\UpdateEventOccurrenceAction;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Archived;
use Filament\Actions\Action;
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
            Action::make('archive')
                ->label('Archive')
                ->color('warning')
                ->requiresConfirmation()
                ->authorize(fn (EventOccurrence $record): bool => Gate::forUser(Filament::auth()->user())->allows('delete', $record))
                ->visible(fn (EventOccurrence $record): bool => $record->status->canTransitionTo(Archived::class))
                ->action(function (EventOccurrence $record): void {
                    app(ArchiveScheduleRecordAction::class)->handle('occurrence', (string) $record->getKey(), Filament::auth()->user());
                    $this->refreshFormData(['status', 'archived_at']);
                }),
        ];
    }
}
