<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Imports;

use AIArmada\Contacting\Models\ContactMethod;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

final class ContactMethodImporter extends Importer
{
    protected static ?string $model = ContactMethod::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('contactable_type')
                ->required(),
            ImportColumn::make('contactable_id')
                ->required(),
            ImportColumn::make('type')
                ->required(),
            ImportColumn::make('label'),
            ImportColumn::make('value')
                ->required(),
            ImportColumn::make('country_code'),
            ImportColumn::make('is_primary')
                ->castStateUsing(fn (?string $state): bool => $state === 'true' || $state === '1' || $state === 'yes'),
            ImportColumn::make('is_public')
                ->castStateUsing(fn (?string $state): bool => $state !== 'false' && $state !== '0' && $state !== 'no'),
        ];
    }

    protected function mutate(array $data): array
    {
        $data['country_code'] = isset($data['country_code'])
            ? mb_strtoupper((string) $data['country_code'])
            : null;

        return $data;
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

    protected function handleRecordCreation(array $data): ContactMethod
    {
        $record = new ContactMethod;
        $record->fill($data);
        $record->save();

        return $record;
    }
}
