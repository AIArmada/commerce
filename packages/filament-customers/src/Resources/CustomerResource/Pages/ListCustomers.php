<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\Pages;

use AIArmada\FilamentCustomers\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
