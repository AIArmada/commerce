<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Imports;

use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Support\ContactingModelReferenceGuard;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

final class ContactMethodImporter extends Importer
{
    protected static ?string $model = ContactMethod::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('contactable_type')
                ->requiredMapping(),
            ImportColumn::make('contactable_id')
                ->requiredMapping(),
            ImportColumn::make('type')
                ->requiredMapping(),
            ImportColumn::make('label'),
            ImportColumn::make('value')
                ->requiredMapping(),
            ImportColumn::make('country_code'),
            ImportColumn::make('is_primary')
                ->castStateUsing(fn (?string $state): bool => $state === 'true' || $state === '1' || $state === 'yes'),
            ImportColumn::make('is_public')
                ->castStateUsing(fn (?string $state): bool => $state !== 'false' && $state !== '0' && $state !== 'no'),
        ];
    }

    protected function beforeValidate(): void
    {
        app(ContactingModelReferenceGuard::class)->resolve(
            $this->data['contactable_type'] ?? null,
            $this->data['contactable_id'] ?? null,
        );

        $this->data['country_code'] = isset($this->data['country_code'])
            ? mb_strtoupper((string) $this->data['country_code'])
            : null;
    }

    public function resolveRecord(): ?ContactMethod
    {
        return new ContactMethod;
    }

    /**
     * @return class-string<ContactMethod>
     */
    public static function getModelLabel(): string
    {
        return ContactMethod::class;
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
}
