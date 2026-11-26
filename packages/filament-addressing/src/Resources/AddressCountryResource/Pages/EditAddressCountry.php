<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressCountryResource\Pages;

use AIArmada\Addressing\Contracts\GeographyReferenceEditor;

use AIArmada\FilamentAddressing\Resources\AddressCountryResource;
use AIArmada\FilamentAddressing\Persistence\AddressingFilterOptions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAddressCountry extends EditRecord
{
    protected static string $resource = AddressCountryResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressCountryResource::isReadOnly() && parent::canAccess($parameters);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(GeographyReferenceEditor::class)->country((string) $record->getKey(), $data);
        AddressingFilterOptions::forgetCountryOptions();

        return $record->fresh() ?? $record;
    }
}
