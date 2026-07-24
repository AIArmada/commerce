<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\CredentialDefinitionResource\Pages;

use AIArmada\FilamentPersons\Resources\CredentialDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditCredentialDefinition extends EditRecord
{
    protected static string $resource = CredentialDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
