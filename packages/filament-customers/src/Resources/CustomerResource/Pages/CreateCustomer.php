<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\Pages;

use AIArmada\FilamentCustomers\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}
