<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer;

use AIArmada\Events\Models\Venue;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

final class VenueImporter extends Importer
{
    protected static ?string $model = Venue::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->label('Name'),
            ImportColumn::make('slug')
                ->label('Slug'),
            ImportColumn::make('venue_type')
                ->requiredMapping()
                ->label('Venue Type'),
            ImportColumn::make('line1')
                ->label('Address Line 1'),
            ImportColumn::make('city')
                ->label('City'),
            ImportColumn::make('state')
                ->label('State'),
            ImportColumn::make('country')
                ->label('Country'),
            ImportColumn::make('status')
                ->requiredMapping()
                ->label('Status'),
            ImportColumn::make('phone')
                ->label('Phone'),
            ImportColumn::make('email')
                ->label('Email'),
        ];
    }

    public function resolveRecord(): ?Venue
    {
        return new Venue;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your venue import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
