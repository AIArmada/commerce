<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages;

use AIArmada\Addressing\Contracts\PostalCodeWriter;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreatePostalCode extends CreateRecord
{
    protected static string $resource = PostalCodeResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! PostalCodeResource::isReadOnly() && parent::canAccess($parameters);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $modelClass = PostalCodeResource::getModel();
        $id = app(PostalCodeWriter::class)->execute($data, modelClass: $modelClass);

        return $modelClass::query()->with('areas')->findOrFail($id);
    }
}
