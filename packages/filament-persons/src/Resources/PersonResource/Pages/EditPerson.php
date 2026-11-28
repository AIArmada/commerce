<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\Pages;

use AIArmada\FilamentPersons\Resources\PersonResource;
use AIArmada\Persons\Features\Identity\Actions\DeletePersonAction;
use AIArmada\Persons\Features\Identity\Actions\UpdatePersonAction;
use AIArmada\Persons\Models\Person;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class EditPerson extends EditRecord
{
    protected static string $resource = PersonResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Person) {
            throw new InvalidArgumentException('Invalid person record.');
        }

        return app(UpdatePersonAction::class)->execute($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->using(fn (Person $record): bool => app(DeletePersonAction::class)->execute($record)),
        ];
    }
}
