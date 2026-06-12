<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Resources\ContactMethodResource\Pages;

use AIArmada\FilamentContacting\Resources\ContactMethodResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateContactMethod extends CreateRecord
{
    protected static string $resource = ContactMethodResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['country_code'] = isset($data['country_code'])
            ? mb_strtoupper((string) $data['country_code'])
            : null;

        return $data;
    }
}
