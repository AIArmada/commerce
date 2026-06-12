<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer;

use AIArmada\Events\Models\EventSession;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

final class EventSessionImporter extends Importer
{
    protected static ?string $model = EventSession::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('event_id')
                ->requiredMapping()
                ->label('Event ID'),
            ImportColumn::make('event_occurrence_id')
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

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your event session import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
