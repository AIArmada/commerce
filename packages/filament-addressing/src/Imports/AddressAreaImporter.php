<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Imports;

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Support\SingleAddressAreaSource;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use AIArmada\FilamentAddressing\Support\AddressingFilterOptions;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use JsonException;
use LogicException;

class AddressAreaImporter extends Importer
{
    public static function getModel(): string
    {
        return config('filament-addressing.resources.areas.model', AddressArea::class);
    }

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
            ImportColumn::make('hierarchy_type')
                ->label('Hierarchy Type')
                ->example('administrative'),
            ImportColumn::make('relationship_type')
                ->label('Parent Relationship Type')
                ->example('contains'),
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
            ImportColumn::make('metadata')
                ->label('Metadata')
                ->example('{"source":"import"}')
                ->castStateUsing(static fn (?string $state): array => self::decodeJsonColumn($state, 'metadata')),
            ImportColumn::make('source_payload')
                ->label('Source Payload')
                ->example('{"source":"upstream"}')
                ->castStateUsing(static fn (?string $state): array => self::decodeJsonColumn($state, 'source payload')),
        ];
    }

    public function resolveRecord(): ?AddressArea
    {
        $source = $this->data['source'] ?? '';
        $sourceId = $this->data['source_id'] ?? '';

        if ($source === '' || $sourceId === '') {
            return null;
        }

        $areaClass = self::getModel();

        $existing = $areaClass::query()
            ->where('source', $source)
            ->where('source_id', $sourceId)
            ->first();

        if ($existing instanceof AddressArea) {
            return $existing;
        }

        $record = new $areaClass;

        if (! $record instanceof AddressArea) {
            throw new LogicException('Configured area model must extend AddressArea.');
        }

        return $record;
    }

    public function getValidationRules(): array
    {
        return [
            'country_code' => ['required', 'string', 'max:2'],
            'type' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:100'],
            'source_id' => ['required', 'string', 'max:255'],
            'hierarchy_type' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function beforeSave(): void
    {
        $record = $this->record;

        if (! $record instanceof AddressArea) {
            throw new RowImportFailedException('Expected AddressArea record');
        }

        if (AddressAreaResource::isReadOnly()) {
            throw new RowImportFailedException('Area import is disabled while the resource is read only.');
        }

        $authorizationRecord = $record;

        if ($record->exists) {
            $areaClass = self::getModel();
            $persistedRecord = $areaClass::query()->whereKey($record->getKey())->firstOrFail();

            if (! $persistedRecord instanceof AddressArea) {
                throw new LogicException('Configured area model must extend AddressArea.');
            }

            $authorizationRecord = $persistedRecord;
        }

        $isAuthorized = $record->exists
            ? AddressAreaResource::canEdit($authorizationRecord)
            : AddressAreaResource::canCreate();

        if (! $isAuthorized) {
            throw new RowImportFailedException('You are not authorized to create or update this area.');
        }

        $areaData = $this->buildAddressAreaData();
        $source = new SingleAddressAreaSource($areaData->source, $areaData);

        $result = app(ImportAddressAreasAction::class)->execute($source);

        if ($result->hasFailures()) {
            throw new RowImportFailedException(implode(
                '; ',
                array_map(
                    static fn ($failure): string => $failure->reason,
                    $result->failures,
                ),
            ));
        }

        $areaClass = self::getModel();

        $this->record = $areaClass::query()
            ->where('source', $areaData->source)
            ->where('source_id', $areaData->sourceId)
            ->first() ?? $record;
    }

    public function saveRecord(): void
    {
        // The core import action already persisted the row.
    }

    private function buildAddressAreaData(): AddressAreaData
    {
        return new AddressAreaData(
            source: (string) $this->data['source'],
            sourceId: (string) $this->data['source_id'],
            countryCode: (string) $this->data['country_code'],
            type: (string) $this->data['type'],
            name: (string) $this->data['name'],
            nativeName: $this->nullableString($this->data['native_name'] ?? null),
            code: $this->nullableString($this->data['code'] ?? null),
            parentSourceId: $this->nullableString($this->data['parent_source_id'] ?? null),
            level: $this->nullableInteger($this->data['level'] ?? null),
            latitude: $this->nullableFloat($this->data['latitude'] ?? null),
            longitude: $this->nullableFloat($this->data['longitude'] ?? null),
            hierarchyType: $this->nullableString($this->data['hierarchy_type'] ?? null),
            relationshipType: $this->nullableString($this->data['relationship_type'] ?? null) ?? 'contains',
            metadata: isset($this->data['metadata']) && is_array($this->data['metadata']) ? $this->data['metadata'] : [],
            sourcePayload: isset($this->data['source_payload']) && is_array($this->data['source_payload']) ? $this->data['source_payload'] : [],
        );
    }

    /** @return array<string, mixed> */
    private static function decodeJsonColumn(?string $state, string $label): array
    {
        if ($state === null || mb_trim($state) === '') {
            return [];
        }

        try {
            $decoded = json_decode($state, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RowImportFailedException(
                "Invalid {$label} JSON: " . $exception->getMessage(),
                previous: $exception,
            );
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        // The import finished, so cached filter option lists may be stale.
        AddressingFilterOptions::forgetAreaOptions();

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
