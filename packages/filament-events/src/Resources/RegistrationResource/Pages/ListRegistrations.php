<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\RegistrationResource\Pages;

use AIArmada\FilamentEvents\Resources\RegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
