<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\TrackedPropertyResource\Pages;

use AIArmada\Signals\Features\Ingestion\Api\PropertyAdministration;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Model;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\TrackedPropertyResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

final class CreateTrackedProperty extends CreateRecord
{
    protected static string $resource = TrackedPropertyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(PropertyAdministration::class)->create($data);

        return TrackedProperty::query()->findOrFail($id);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! is_string($data['write_key'] ?? null) || $data['write_key'] === '') {
            $data['write_key'] = Str::random(40);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
