<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\Pages;

use AIArmada\FilamentOrders\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
}
