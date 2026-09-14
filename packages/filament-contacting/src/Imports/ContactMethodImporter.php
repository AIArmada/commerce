<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Imports;

use AIArmada\Contacting\Enums\ContactMethodType;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Support\ContactingModelReferenceGuard;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class ContactMethodImporter extends Importer
{
    protected static ?string $model = ContactMethod::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('contactable_type')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('contactable_id')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('type')
                ->requiredMapping()
                ->rules(fn (): array => ['required', 'string', Rule::in(array_keys(ContactMethodType::options(config('contacting.contact_methods.types', []))))]),
            ImportColumn::make('label')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('value')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:2048']),
            ImportColumn::make('country_code')
                ->rules(['nullable', 'alpha', 'size:2']),
            ImportColumn::make('is_primary')
                ->castStateUsing(fn (?string $state): bool => $state === 'true' || $state === '1' || $state === 'yes')
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('is_public')
                ->castStateUsing(fn (?string $state): ?bool => self::castNullableBoolean($state))
                ->rules(['nullable', 'boolean']),
        ];
    }

    protected function beforeValidate(): void
    {
        try {
            app(ContactingModelReferenceGuard::class)->resolve(
                $this->data['contactable_type'] ?? null,
                $this->data['contactable_id'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw new RowImportFailedException($exception->getMessage(), previous: $exception);
        }

        $this->data['country_code'] = isset($this->data['country_code'])
            ? mb_strtoupper((string) $this->data['country_code'])
            : null;
    }

    /**
     * Imports are insert-only: every row creates a new record. Re-running an
     * import re-inserts rows instead of updating them (core unique backstops
     * reject exact duplicates where they apply). Match-then-update is
     * intentionally not implemented because contact methods have no natural
     * unique key.
     */
    public function resolveRecord(): ?ContactMethod
    {
        return new ContactMethod;
    }

    public function saveRecord(): void
    {
        try {
            parent::saveRecord();
        } catch (QueryException $exception) {
            throw new RowImportFailedException($this->saveFailureMessage($exception), previous: $exception);
        }
    }

    public static function getModelLabel(): string
    {
        return 'Contact Method';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your contact method import has completed and '
            . number_format($import->successful_rows) . ' '
            . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }

    private function saveFailureMessage(QueryException $exception): string
    {
        if ($exception->getCode() === '23000') {
            return 'This contact method already exists and imports never update existing records.';
        }

        return 'This contact method could not be saved.';
    }

    private static function castNullableBoolean(?string $state): ?bool
    {
        if ($state === null || mb_trim($state) === '') {
            return null;
        }

        return ! in_array(mb_strtolower(mb_trim($state)), ['false', '0', 'no'], true);
    }
}
