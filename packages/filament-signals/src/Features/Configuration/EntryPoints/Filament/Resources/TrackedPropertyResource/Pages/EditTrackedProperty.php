<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\TrackedPropertyResource\Pages;

use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\TrackedPropertyResource;
use AIArmada\Signals\Features\Ingestion\Api\PropertyAdministration;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditTrackedProperty extends EditRecord
{
    protected static string $resource = TrackedPropertyResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(PropertyAdministration::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
