<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\CredentialDefinitionResource\Pages;

use AIArmada\FilamentPersons\Resources\CredentialDefinitionResource;
use AIArmada\Persons\Features\Credentials\Actions\DeleteCredentialDefinition;
use AIArmada\Persons\Features\Credentials\Actions\SaveCredentialDefinition;
use AIArmada\Persons\Features\Credentials\Models\CredentialDefinition;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class EditCredentialDefinition extends EditRecord
{
    protected static string $resource = CredentialDefinitionResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof CredentialDefinition) {
            throw new InvalidArgumentException('Invalid CredentialDefinition record.');
        }

        return app(SaveCredentialDefinition::class)->update($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->using(fn (CredentialDefinition $record): bool => app(DeleteCredentialDefinition::class)->execute($record)),
        ];
    }
}
