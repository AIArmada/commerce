<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer;

use AIArmada\Events\Contracts\EventImportScopeValidator;
use AIArmada\Events\Features\Catalog\Actions\ImportEventSessionAction;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\FilamentEvents\Actions\Importer\Concerns\AuthorizesImportedRecords;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use LogicException;

final class EventSessionImporter extends Importer
{
    use AuthorizesImportedRecords;

    /**
     * @return class-string<EventSession>
     */
    public static function getModel(): string
    {
        /** @var class-string<EventSession> $model */
        $model = ModelResolver::sessionClass();

        return $model;
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('event_id')
                ->requiredMapping()
                ->rules(['required', 'uuid'])
                ->label('Event ID'),
            ImportColumn::make('event_occurrence_id')
                ->requiredMapping()
                ->rules(['required', 'uuid'])
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
        $sessionClass = static::getModel();
        $record = new $sessionClass;

        if (! $record instanceof EventSession) {
            throw new LogicException('Configured session model must extend EventSession.');
        }

        return $record;
    }

    public function beforeCreate(): void
    {
        $this->authorizeRecordCreate();
        $this->assertEventScope($this->data['event_id'] ?? null, $this->data['event_occurrence_id'] ?? null);
    }

    protected function beforeUpdate(): void
    {
        $this->authorizeRecordUpdate();

        $record = $this->record;
        $eventId = array_key_exists('event_id', $this->data)
            ? $this->data['event_id']
            : $record?->getAttribute('event_id');
        $occurrenceId = array_key_exists('event_occurrence_id', $this->data)
            ? $this->data['event_occurrence_id']
            : $record?->getAttribute('event_occurrence_id');

        $this->assertEventScope($eventId, $occurrenceId);
    }

    private function assertEventScope(mixed $eventId, mixed $occurrenceId): void
    {
        app(EventImportScopeValidator::class)->validate($eventId, $occurrenceId, $this->resolveImportUser(), 'update');
    }

    public function saveRecord(): void
    {
        $this->record = app(ImportEventSessionAction::class)->handle($this->data, $this->resolveImportUser());
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
