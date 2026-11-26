<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressStateResource\Pages;

use AIArmada\Addressing\Contracts\GeographyReferenceEditor;

use AIArmada\FilamentAddressing\Resources\AddressStateResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAddressState extends EditRecord
{
    protected static string $resource = AddressStateResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressStateResource::isReadOnly() && parent::canAccess($parameters);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(GeographyReferenceEditor::class)->state((string) $record->getKey(), $data);

        return $record->fresh() ?? $record;
    }
}
