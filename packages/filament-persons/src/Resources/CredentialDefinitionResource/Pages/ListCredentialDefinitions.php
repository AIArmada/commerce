<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\CredentialDefinitionResource\Pages;

use AIArmada\FilamentPersons\Resources\CredentialDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListCredentialDefinitions extends ListRecords
{
    protected static string $resource = CredentialDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
