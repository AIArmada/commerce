<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\RegistrationResource\Pages;

use AIArmada\FilamentEvents\Resources\RegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewRegistration extends ViewRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            RegistrationResource::approveAction(),
            RegistrationResource::rejectAction(),
        ];
    }
}
