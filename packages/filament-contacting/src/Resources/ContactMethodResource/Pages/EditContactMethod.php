<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Resources\ContactMethodResource\Pages;

use AIArmada\FilamentContacting\Resources\ContactMethodResource;
use Filament\Resources\Pages\EditRecord;

final class EditContactMethod extends EditRecord
{
    protected static string $resource = ContactMethodResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['country_code'] = isset($data['country_code'])
            ? mb_strtoupper((string) $data['country_code'])
            : null;

        return $data;
    }
}
