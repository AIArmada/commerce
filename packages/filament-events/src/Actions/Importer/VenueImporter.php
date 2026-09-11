<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer;

use AIArmada\Addressing\Models\Address;
use AIArmada\Events\Models\Venue;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use InvalidArgumentException;
use LogicException;

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
            ImportColumn::make('address_id')
                ->label('Primary Address ID')
                ->rules(['uuid'])
                ->ignoreBlankState()
                ->fillRecordUsing(static function (): void {})
                ->saveRelationshipsUsing(function (ImportColumn $column, mixed $state): void {
                    if (! is_string($state) || $state === '') {
                        throw new InvalidArgumentException('A valid primary address ID is required when address_id is mapped.');
                    }

                    $record = $column->getRecord();

                    if (! $record instanceof Venue) {
                        throw new LogicException('Venue importer did not resolve a Venue record.');
                    }

                    $address = Address::query()->findOrFail($state);

                    $record->attachAddress($address, type: 'primary', isPrimary: true);
                }),
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
