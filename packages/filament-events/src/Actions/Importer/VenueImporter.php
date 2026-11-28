<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer;

use AIArmada\Events\Features\Venues\Actions\ImportVenueAction;
use AIArmada\Events\Features\Venues\Models\Venue;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\FilamentEvents\Actions\Importer\Concerns\AuthorizesImportedRecords;
use AIArmada\FilamentEvents\Actions\Importer\Concerns\ReadsMappedImportData;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use LogicException;

final class VenueImporter extends Importer
{
    use AuthorizesImportedRecords;
    use ReadsMappedImportData;

    /**
     * @return class-string<Venue>
     */
    public static function getModel(): string
    {
        /** @var class-string<Venue> $model */
        $model = ModelResolver::venueClass();

        return $model;
    }

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
                ->fillRecordUsing(static function (): void {}),
            ImportColumn::make('status')
                ->requiredMapping()
                ->label('Status'),
            ImportColumn::make('phone')
                ->label('Phone')
                ->ignoreBlankState()
                ->fillRecordUsing(static function (): void {}),
            ImportColumn::make('email')
                ->label('Email')
                ->rules(['nullable', 'email'])
                ->ignoreBlankState()
                ->fillRecordUsing(static function (): void {}),
        ];
    }

    public function resolveRecord(): ?Venue
    {
        $venueClass = static::getModel();
        $record = new $venueClass;

        if (! $record instanceof Venue) {
            throw new LogicException('Configured venue model must extend Venue.');
        }

        return $record;
    }

    protected function beforeCreate(): void
    {
        $this->authorizeRecordCreate();
    }

    protected function beforeUpdate(): void
    {
        $this->authorizeRecordUpdate();
    }

    public function saveRecord(): void
    {
        $record = $this->record;

        if (! $record instanceof Venue) {
            throw new LogicException('Venue importer did not resolve a Venue record.');
        }

        $this->record = app(ImportVenueAction::class)->handle($record->exists ? (string) $record->getKey() : null, $this->mappedImportData(), $this->resolveImportUser());
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
