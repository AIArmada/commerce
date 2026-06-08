<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\RegistrationResource\Pages;

use AIArmada\FilamentEvents\Resources\RegistrationResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateRegistration extends CreateRecord
{
    protected static string $resource = RegistrationResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return RegistrationResource::normalizeCreateData($data);
    }
}
