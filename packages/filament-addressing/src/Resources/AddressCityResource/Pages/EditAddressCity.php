<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressCityResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressCityResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAddressCity extends EditRecord
{
    protected static string $resource = AddressCityResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressCityResource::isReadOnly();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update([
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]);

        return $record->fresh() ?? $record;
    }
}
