<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Imports;

use AIArmada\Contacting\Enums\SocialPlatform;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\Contacting\Support\ContactingModelReferenceGuard;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class SocialProfileImporter extends Importer
{
    protected static ?string $model = SocialProfile::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('socialable_type')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('socialable_id')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('platform')
                ->requiredMapping()
                ->rules(fn (): array => ['required', 'string', Rule::in(array_keys(SocialPlatform::options()))]),
            ImportColumn::make('label')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('handle')
                ->rules(['nullable', 'string', 'max:255', 'required_without:url']),
            ImportColumn::make('url')
                ->rules(['nullable', 'url:http,https', 'max:2048', 'required_without:handle']),
            ImportColumn::make('display_name')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('is_primary')
                ->castStateUsing(fn (?string $state): bool => $state === 'true' || $state === '1' || $state === 'yes')
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('is_public')
                ->castStateUsing(fn (?string $state): ?bool => self::castNullableBoolean($state))
                ->rules(['nullable', 'boolean']),
        ];
    }

    /**
     * Imports are insert-only: every row creates a new record. Re-running an
     * import re-inserts rows instead of updating them (core unique backstops
     * reject exact duplicates where they apply). Match-then-update is
     * intentionally not implemented because social profiles have no natural
     * unique key.
     */
    public function resolveRecord(): ?SocialProfile
    {
        return new SocialProfile;
    }

    public function saveRecord(): void
    {
        try {
            parent::saveRecord();
        } catch (QueryException $exception) {
            throw new RowImportFailedException($this->saveFailureMessage($exception), previous: $exception);
        }
    }

    protected function beforeValidate(): void
    {
        try {
            app(ContactingModelReferenceGuard::class)->resolve(
                $this->data['socialable_type'] ?? null,
                $this->data['socialable_id'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw new RowImportFailedException($exception->getMessage(), previous: $exception);
        }
    }

    protected function beforeSave(): void
    {
        if (blank($this->data['handle'] ?? null) && blank($this->data['url'] ?? null)) {
            throw new RowImportFailedException('Either a handle or a URL is required.');
        }
    }

    public static function getModelLabel(): string
    {
        return 'Social Profile';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your social profile import has completed and '
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
            return 'This social profile already exists and imports never update existing records.';
        }

        return 'This social profile could not be saved.';
    }

    private static function castNullableBoolean(?string $state): ?bool
    {
        if ($state === null || mb_trim($state) === '') {
            return null;
        }

        return ! in_array(mb_strtolower(mb_trim($state)), ['false', '0', 'no'], true);
    }
}
