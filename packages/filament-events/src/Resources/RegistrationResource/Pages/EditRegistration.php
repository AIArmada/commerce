<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\RegistrationResource\Pages;

use AIArmada\FilamentEvents\Resources\RegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
