<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer;

use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Features\Venues\Models\Venue;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\FilamentEvents\Actions\Importer\Concerns\AuthorizesImportedRecords;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

final class VenueImporter extends Importer
{
    use AuthorizesImportedRecords;

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
                ->fillRecordUsing(static function (): void {})
                ->saveRelationshipsUsing(function (ImportColumn $column, mixed $state): void {
                    if (! is_string($state) || $state === '') {
                        throw new InvalidArgumentException('A valid primary address ID is required when address_id is mapped.');
                    }

                    $record = $column->getRecord();

                    if (! $record instanceof Venue) {
                        throw new LogicException('Venue importer did not resolve a Venue record.');
                    }

                    try {
                        if (method_exists(Address::class, 'ownerScopeConfig') && ! Address::ownerScopeConfig()->enabled) {
                            $address = Address::query()->findOrFail($state);
                        } else {
                            $address = OwnerWriteGuard::findOrFailForOwner(Address::class, $state);
                        }
                    } catch (AuthorizationException | ModelNotFoundException) {
                        throw ValidationException::withMessages([
                            'address_id' => 'The selected address does not exist in the current scope.',
                        ]);
                    }

                    $record->attachAddress($address, type: 'primary', isPrimary: true);
                }),
            ImportColumn::make('status')
                ->requiredMapping()
                ->label('Status'),
            ImportColumn::make('phone')
                ->label('Phone'),
            ImportColumn::make('email')
                ->label('Email'),
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

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your venue import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
