<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources\CommunicationDeliveryResource\Pages;

use AIArmada\Filament\Communications\Resources\CommunicationDeliveryResource;
use Filament\Resources\Pages\ListRecords;

final class ListCommunicationDeliveries extends ListRecords
{
    protected static string $resource = CommunicationDeliveryResource::class;
}
