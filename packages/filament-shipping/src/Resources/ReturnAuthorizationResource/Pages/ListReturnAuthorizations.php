<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages;

use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReturnAuthorizations extends ListRecords
{
    protected static string $resource = ReturnAuthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
