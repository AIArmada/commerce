<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\TrackedPropertyResource\Pages;

use AIArmada\FilamentSignals\Resources\TrackedPropertyResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

final class CreateTrackedProperty extends CreateRecord
{
    protected static string $resource = TrackedPropertyResource::class;

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
