<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages;

use AIArmada\Addressing\Actions\SavePostalCodeAction;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class CreatePostalCode extends CreateRecord
{
    protected static string $resource = PostalCodeResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! PostalCodeResource::isReadOnly();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $modelClass = PostalCodeResource::getModel();
        $record = new $modelClass;

        if (! $record instanceof PostalCode) {
            throw new LogicException('Configured postcode model must extend PostalCode.');
        }

        return app(SavePostalCodeAction::class)->execute($data, $record);
    }
}
