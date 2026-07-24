<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\CredentialDefinitionResource\Pages;

use AIArmada\FilamentPersons\Resources\CredentialDefinitionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCredentialDefinition extends CreateRecord
{
    protected static string $resource = CredentialDefinitionResource::class;
}
