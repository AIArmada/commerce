<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer;

use AIArmada\Events\Models\EventRegistration;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

final class EventRegistrationImporter extends Importer
{
    protected static ?string $model = EventRegistration::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('event_id')
                ->requiredMapping()
                ->label('Event ID'),
            ImportColumn::make('event_occurrence_id')
                ->label('Occurrence ID'),
            ImportColumn::make('registration_type')
                ->requiredMapping()
                ->label('Registration Type'),
            ImportColumn::make('status')
                ->requiredMapping()
                ->label('Status'),
            ImportColumn::make('source')
                ->requiredMapping()
                ->label('Source'),
            ImportColumn::make('total_participants')
                ->numeric()
                ->label('Total Participants'),
            ImportColumn::make('total_amount')
                ->numeric()
                ->label('Total Amount'),
            ImportColumn::make('currency')
                ->label('Currency'),
        ];
    }

    public function resolveRecord(): ?EventRegistration
    {
        return new EventRegistration;
    }
}
