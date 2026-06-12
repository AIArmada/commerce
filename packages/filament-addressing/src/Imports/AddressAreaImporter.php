<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Imports;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use Exception;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Str;

class AddressAreaImporter extends Importer
{
    protected static ?string $model = AddressArea::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('country_code')
                ->label('Country Code')
                ->requiredMapping()
                ->example('MY'),
            ImportColumn::make('type')
                ->label('Type')
                ->requiredMapping()
                ->example('state'),
            ImportColumn::make('level')
                ->label('Level')
                ->numeric()
                ->example('1'),
            ImportColumn::make('name')
                ->label('Name')
                ->requiredMapping()
                ->example('Selangor'),
            ImportColumn::make('native_name')
                ->label('Native Name')
                ->example('Selangor'),
            ImportColumn::make('code')
                ->label('Code')
                ->example('10'),
            ImportColumn::make('parent_source_id')
                ->label('Parent Source ID')
                ->example('MYS'),
            ImportColumn::make('source')
                ->label('Source')
                ->requiredMapping()
                ->example('app.malaysia'),
            ImportColumn::make('source_id')
                ->label('Source ID')
                ->requiredMapping()
                ->example('MY-10'),
            ImportColumn::make('latitude')
                ->label('Latitude')
                ->numeric()
                ->example('3.0738'),
            ImportColumn::make('longitude')
                ->label('Longitude')
                ->numeric()
                ->example('101.5183'),
        ];
    }

    public function resolveRecord(): ?AddressArea
    {
        $source = $this->data['source'] ?? '';
        $sourceId = $this->data['source_id'] ?? '';

        if ($source === '' || $sourceId === '') {
            return null;
        }

        $existing = AddressArea::query()
            ->where('source', $source)
            ->where('source_id', $sourceId)
            ->first();

        return $existing ?? new AddressArea;
    }

    public function getValidationRules(): array
    {
        return [
            'country_code' => ['required', 'string', 'max:2'],
            'type' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:100'],
            'source_id' => ['required', 'string', 'max:255'],
        ];
    }

    protected function beforeSave(): void
    {
        $record = $this->record;

        if (! $record instanceof AddressArea) {
            throw new Exception('Expected AddressArea record');
        }

        $countryCode = $this->data['country_code'] ?? '';

        $country = AddressCountry::where('iso2', $countryCode)->first();

        if ($country === null) {
            throw new Exception("Country not found for countryCode: {$countryCode}");
        }

        $parentSourceId = $this->data['parent_source_id'] ?? null;

        if ($parentSourceId !== null && $parentSourceId !== '') {
            $parent = AddressArea::query()
                ->where('source', $this->data['source'] ?? '')
                ->where('source_id', $parentSourceId)
                ->first();

            if ($parent === null) {
                throw new Exception("Parent not found for parent_source_id: {$parentSourceId}");
            }

            $record->parent_id = $parent->id;
        }

        $record->country_id = $country->id;
        $record->slug = Str::slug($this->data['name']);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your area import has completed and '
            . number_format($import->successful_rows) . ' '
            . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
