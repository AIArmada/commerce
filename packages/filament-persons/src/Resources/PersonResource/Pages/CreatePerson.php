<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\Pages;

use AIArmada\FilamentPersons\Resources\PersonResource;
use AIArmada\Persons\Features\Identity\Actions\CreatePersonAction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreatePerson extends CreateRecord
{
    protected static string $resource = PersonResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatePersonAction::class)->execute($data);
    }
}
