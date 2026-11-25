<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\${kind}Resource\Pages;

use AIArmada\FilamentAuthz\Resources\${kind}Resource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->using(fn (Model $record): bool => static::getResource()::deleteDefinition($record)),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return static::getResource()::saveDefinition($data, $record);
    }
}
