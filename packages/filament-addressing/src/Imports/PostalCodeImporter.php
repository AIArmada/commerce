<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Imports;

use AIArmada\Addressing\Actions\ImportPostalCodeAdminDataAction;
use AIArmada\Addressing\Actions\ImportPostalCodesAction;
use AIArmada\Addressing\Data\PostalCodeData;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Support\ArrayPostalCodeSource;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;

class PostalCodeImporter extends Importer
{
    public static function getModel(): string
    {
        return config('filament-addressing.resources.postal_codes.model', PostalCode::class);
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('country_code')
                ->label('Country Code')
                ->requiredMapping()
                ->example('MY'),
            ImportColumn::make('code')
                ->label('Postcode')
                ->requiredMapping()
                ->example('47500'),
            ImportColumn::make('source')
                ->label('Source')
                ->example('app.malaysia'),
            ImportColumn::make('source_id')
                ->label('Source ID')
                ->example('MY-47500'),
            ImportColumn::make('area_source')
                ->label('Area Source')
                ->example('app.malaysia'),
            ImportColumn::make('area_source_id')
                ->label('Area Source ID')
                ->example('MY-10-PETALING'),
            ImportColumn::make('relationship_type')
                ->label('Relationship Type')
                ->example('served_by'),
            ImportColumn::make('is_primary')
                ->label('Is Primary')
                ->boolean()
                ->example('0'),
            ImportColumn::make('metadata')
                ->label('Metadata')
                ->example('{"source":"import"}')
                ->castStateUsing(static function (?string $state): ?array {
                    if ($state === null || mb_trim($state) === '') {
                        return null;
                    }

                    try {
                        $decoded = json_decode($state, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $exception) {
                        throw new RowImportFailedException(
                            'Invalid metadata JSON: ' . $exception->getMessage(),
                            previous: $exception,
                        );
                    }

                    if ($decoded !== null && ! is_array($decoded)) {
                        throw new RowImportFailedException('Metadata JSON must be an object, a list, or null.');
                    }

                    return $decoded;
                }),
            ImportColumn::make('is_active')
                ->label('Active')
                ->boolean()
                ->example('1'),
            ImportColumn::make('coverage')
                ->label('Coverage')
                ->example('[{"source":"provider","source_id":"MY-47500:area-1","area_source":"app.malaysia","area_source_id":"MY-10","relationship_type":"served_by","is_primary":true}]')
                ->castStateUsing(static function (?string $state): ?array {
                    if ($state === null || mb_trim($state) === '') {
                        return null;
                    }

                    try {
                        $decoded = json_decode($state, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $exception) {
                        throw new RowImportFailedException(
                            'Invalid coverage JSON: ' . $exception->getMessage(),
                            previous: $exception,
                        );
                    }

                    if (! is_array($decoded) || ! array_is_list($decoded)) {
                        throw new RowImportFailedException('Coverage JSON must be a list of coverage objects.');
                    }

                    return $decoded;
                }),
        ];
    }

    public function resolveRecord(): ?PostalCode
    {
        $countryCode = mb_strtoupper(mb_trim((string) ($this->data['country_code'] ?? '')));
        $code = mb_trim((string) ($this->data['code'] ?? ''));

        if ($countryCode === '' || $code === '') {
            return null;
        }

        $postalClass = self::getModel();

        $existing = $postalClass::query()
            ->where('country_code', $countryCode)
            ->where('code', $code)
            ->first();

        if ($existing instanceof PostalCode) {
            return $existing;
        }

        $record = new $postalClass;

        if (! $record instanceof PostalCode) {
            throw new LogicException('Configured postal code model must extend PostalCode.');
        }

        return $record;
    }

    public function getValidationRules(): array
    {
        return [
            'country_code' => ['required', 'string', 'max:2'],
            'code' => ['required', 'string', 'max:20'],
            'source' => ['nullable', 'string', 'max:100'],
            'source_id' => ['nullable', 'string', 'max:255'],
            'relationship_type' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'coverage' => ['nullable', 'array'],
            'coverage.*' => ['array'],
        ];
    }

    protected function beforeSave(): void
    {
        $record = $this->record;

        if (! $record instanceof PostalCode) {
            throw new RowImportFailedException('Expected PostalCode record');
        }

        $persistedRecord = $record->exists
            ? $record->newQuery()->whereKey($record->getKey())->first()
            : $record;

        if (! $persistedRecord instanceof PostalCode) {
            throw new RowImportFailedException('The postcode could not be found for import.');
        }

        if (PostalCodeResource::isReadOnly()
            || ($persistedRecord->exists
                ? ! PostalCodeResource::canEdit($persistedRecord)
                : ! PostalCodeResource::canCreate())) {
            throw new RowImportFailedException('You are not authorized to import this postcode.');
        }

        $record = $persistedRecord;

        $coverage = $this->data['coverage'] ?? null;

        if (is_array($coverage)) {
            if ($this->hasFlatCoverage()) {
                throw new RowImportFailedException('Use either the coverage JSON column or the flat area source columns, not both.');
            }

            try {
                $this->record = app(ImportPostalCodeAdminDataAction::class)->execute(
                    countryCode: (string) $this->data['country_code'],
                    code: (string) $this->data['code'],
                    isActive: filter_var($this->data['is_active'] ?? ($record->exists ? $record->is_active : true), FILTER_VALIDATE_BOOLEAN),
                    metadata: array_key_exists('metadata', $this->data)
                        ? (is_array($this->data['metadata']) ? $this->data['metadata'] : null)
                        : $record->metadata,
                    coverage: $coverage,
                );
            } catch (ValidationException $exception) {
                throw new RowImportFailedException(implode('; ', $exception->validator->errors()->all()), previous: $exception);
            }

            return;
        }

        if (! is_string($this->data['source'] ?? null) || mb_trim($this->data['source']) === ''
            || ! is_string($this->data['source_id'] ?? null) || mb_trim($this->data['source_id']) === '') {
            throw new RowImportFailedException('Source and Source ID are required for flat postcode imports.');
        }

        $postalData = $this->buildPostalCodeData();
        $source = new ArrayPostalCodeSource($postalData->source, [$postalData]);

        $result = app(ImportPostalCodesAction::class)->execute($source);

        if ($result->hasFailures()) {
            throw new RowImportFailedException(implode(
                '; ',
                array_map(
                    static fn ($failure): string => $failure->reason,
                    $result->failures,
                ),
            ));
        }

        $postalClass = self::getModel();

        $this->record = $postalClass::query()
            ->where('country_code', $postalData->countryCode)
            ->where('code', $postalData->code)
            ->first() ?? $record;
    }

    public function saveRecord(): void
    {
        // The core import action already persisted the row.
    }

    private function buildPostalCodeData(): PostalCodeData
    {
        return new PostalCodeData(
            source: (string) $this->data['source'],
            sourceId: (string) $this->data['source_id'],
            countryCode: (string) $this->data['country_code'],
            code: (string) $this->data['code'],
            areaSource: $this->nullableString($this->data['area_source'] ?? null),
            areaSourceId: $this->nullableString($this->data['area_source_id'] ?? null),
            relationshipType: $this->nullableString($this->data['relationship_type'] ?? null) ?? 'served_by',
            isPrimary: (bool) ($this->data['is_primary'] ?? false),
            metadata: isset($this->data['metadata']) && is_array($this->data['metadata']) ? $this->data['metadata'] : [],
        );
    }

    private function hasFlatCoverage(): bool
    {
        return $this->nullableString($this->data['area_source'] ?? null) !== null
            || $this->nullableString($this->data['area_source_id'] ?? null) !== null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your postcode import has completed and '
            . number_format($import->successful_rows) . ' '
            . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
