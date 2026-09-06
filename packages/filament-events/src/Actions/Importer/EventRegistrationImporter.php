<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer;

use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Support\ModelResolver;
use Closure;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

final class EventRegistrationImporter extends Importer
{
    /**
     * @return class-string<EventRegistration>
     */
    public static function getModel(): string
    {
        return ModelResolver::registrationClass();
    }

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
                ->rules([
                    function (string $attribute, mixed $value, Closure $fail): void {
                        if (! is_string($value)) {
                            $fail('A valid registration status is required.');

                            return;
                        }

                        try {
                            static::newRegistration()->initializeStatus($value);
                        } catch (InvalidArgumentException | LogicException $exception) {
                            $fail($exception->getMessage());
                        }
                    },
                ])
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
        $record = $this->record;
        $status = $this->data['status'] ?? null;

        if (! $record instanceof EventRegistration || ! is_string($status)) {
            throw ValidationException::withMessages([
                'status' => 'A valid registration status is required.',
            ]);
        }

        try {
            $record->initializeStatus($status);
        } catch (InvalidArgumentException | LogicException $exception) {
            throw ValidationException::withMessages([
                'status' => $exception->getMessage(),
            ]);
        }
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
