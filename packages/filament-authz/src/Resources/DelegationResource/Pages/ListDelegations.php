<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\DelegationResource\Pages;

use AIArmada\FilamentAuthz\Resources\DelegationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDelegations extends ListRecords
{
    protected static string $resource = DelegationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
