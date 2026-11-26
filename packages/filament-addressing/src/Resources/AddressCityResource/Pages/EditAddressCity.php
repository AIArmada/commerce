<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressCityResource\Pages;

use AIArmada\Addressing\Contracts\GeographyReferenceEditor;

use AIArmada\FilamentAddressing\Resources\AddressCityResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAddressCity extends EditRecord
{
    protected static string $resource = AddressCityResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressCityResource::isReadOnly() && parent::canAccess($parameters);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(GeographyReferenceEditor::class)->city((string) $record->getKey(), $data);

        return $record->fresh() ?? $record;
    }
}
