<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\${kind}Resource\Pages;

use AIArmada\FilamentAuthz\Resources\${kind}Resource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return static::getResource()::saveDefinition($data);
    }
}
