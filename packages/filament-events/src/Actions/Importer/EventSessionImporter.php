<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

final class EventSessionImporter extends Importer
{
    protected static ?string $model = EventSession::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('event_id')
                ->requiredMapping()
                ->rules(['required', 'uuid'])
                ->label('Event ID'),
            ImportColumn::make('event_occurrence_id')
                ->rules(['nullable', 'uuid'])
                ->label('Occurrence ID'),
            ImportColumn::make('title')
                ->requiredMapping()
                ->label('Title'),
            ImportColumn::make('slug')
                ->label('Slug'),
            ImportColumn::make('summary')
                ->label('Summary'),
            ImportColumn::make('starts_at')
                ->requiredMapping()
                ->label('Starts At'),
            ImportColumn::make('ends_at')
                ->requiredMapping()
                ->label('Ends At'),
            ImportColumn::make('timezone')
                ->requiredMapping()
                ->label('Timezone'),
            ImportColumn::make('status')
                ->requiredMapping()
                ->label('Status'),
            ImportColumn::make('capacity')
                ->numeric()
                ->label('Capacity'),
            ImportColumn::make('sort_order')
                ->numeric()
                ->label('Sort Order'),
        ];
    }

    public function resolveRecord(): ?EventSession
    {
        return new EventSession;
    }

    public function beforeCreate(): void
    {
        $this->assertEventScope($this->data['event_id'] ?? null, $this->data['event_occurrence_id'] ?? null);
    }

    private function assertEventScope(mixed $eventId, mixed $occurrenceId): void
    {
        if (! is_string($eventId) || mb_trim($eventId) === '') {
            throw ValidationException::withMessages([
                'event_id' => 'The selected event does not exist in the current scope.',
            ]);
        }

        try {
            if (method_exists(Event::class, 'ownerScopeConfig') && ! Event::ownerScopeConfig()->enabled) {
                $event = Event::query()->findOrFail($eventId);
            } else {
                $event = OwnerWriteGuard::findOrFailForOwner(Event::class, $eventId);
            }
        } catch (AuthorizationException | ModelNotFoundException) {
            throw ValidationException::withMessages([
                'event_id' => 'The selected event does not exist in the current scope.',
            ]);
        }

        if ($occurrenceId === null || $occurrenceId === '') {
            return;
        }

        $belongsToEvent = EventOccurrence::query()
            ->whereKey($occurrenceId)
            ->where('event_id', $event->getKey())
            ->exists();

        if (! $belongsToEvent) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => 'The selected occurrence does not belong to the selected event.',
            ]);
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your event session import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
