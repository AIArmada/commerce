<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\CredentialDefinitionResource\Pages;

use AIArmada\FilamentPersons\Resources\CredentialDefinitionResource;
use AIArmada\Persons\Features\Credentials\Actions\SaveCredentialDefinition;
use AIArmada\Persons\Features\Credentials\Models\CredentialDefinition;
use Filament\Resources\Pages\CreateRecord;

final class CreateCredentialDefinition extends CreateRecord
{
    protected static string $resource = CredentialDefinitionResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): CredentialDefinition
    {
        return app(SaveCredentialDefinition::class)->create($data);
    }
}
