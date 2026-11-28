<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer;

use AIArmada\Events\Features\Registrations\Actions\ImportEventRegistrationAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\States\RegistrationStatus\RegistrationStatus;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\FilamentEvents\Actions\Importer\Concerns\AuthorizesImportedRecords;
use AIArmada\FilamentEvents\Actions\Importer\Concerns\ReadsMappedImportData;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\Rule;
use LogicException;

final class EventRegistrationImporter extends Importer
{
    use AuthorizesImportedRecords;
    use ReadsMappedImportData;

    /**
     * @return class-string<EventRegistration>
     */
    public static function getModel(): string
    {
        /** @var class-string<EventRegistration> $model */
        $model = ModelResolver::registrationClass();

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
                ->rules(['nullable', 'uuid'])
                ->label('Occurrence ID'),
            ImportColumn::make('registration_type')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:64'])
                ->label('Registration Type'),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required', Rule::in(array_keys(RegistrationStatus::options()))])
                ->fillRecordUsing(static function (): void {})
                ->label('Status'),
            ImportColumn::make('source')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:64'])
                ->label('Source'),
            ImportColumn::make('total_participants')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1', 'max:2147483647'])
                ->label('Total Participants'),
            ImportColumn::make('total_amount')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0'])
                ->label('Total Amount'),
            ImportColumn::make('currency')
                ->rules(['nullable', 'string', 'max:16'])
                ->label('Currency'),
        ];
    }

    public function resolveRecord(): ?EventRegistration
    {
        return static::newRegistration();
    }

    private static function newRegistration(): EventRegistration
    {
        $registrationClass = static::getModel();
        $record = new $registrationClass;

        if (! $record instanceof EventRegistration) {
            throw new LogicException('Configured registration model must extend EventRegistration.');
        }

        return $record;
    }

    protected function beforeCreate(): void
    {
        $this->authorizeRecordCreate();
    }

    public function saveRecord(): void
    {
        $this->record = app(ImportEventRegistrationAction::class)->handle($this->mappedImportData(), $this->resolveImportUser());
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your event registration import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
