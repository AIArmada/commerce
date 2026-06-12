<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer;

use AIArmada\Events\Models\EventTicketType;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

final class EventTicketTypeImporter extends Importer
{
    protected static ?string $model = EventTicketType::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('event_id')
                ->requiredMapping()
                ->label('Event ID'),
            ImportColumn::make('event_occurrence_id')
                ->label('Occurrence ID'),
            ImportColumn::make('name')
                ->requiredMapping()
                ->label('Name'),
            ImportColumn::make('code')
                ->requiredMapping()
                ->label('Code'),
            ImportColumn::make('access_type')
                ->requiredMapping()
                ->label('Access Type'),
            ImportColumn::make('price')
                ->numeric()
                ->label('Price'),
            ImportColumn::make('currency')
                ->requiredMapping()
                ->label('Currency'),
            ImportColumn::make('quota')
                ->numeric()
                ->label('Quota'),
            ImportColumn::make('status')
                ->requiredMapping()
                ->label('Status'),
            ImportColumn::make('admits_quantity')
                ->numeric()
                ->label('Admits Quantity'),
        ];
    }

    public function resolveRecord(): ?EventTicketType
    {
        return new EventTicketType;
    }
}
