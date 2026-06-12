<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTicketTypeResource\Pages;

use AIArmada\FilamentEvents\Resources\EventTicketTypeResource;
use Filament\Resources\Pages\ListRecords;

final class ListEventTicketTypes extends ListRecords
{
    protected static string $resource = EventTicketTypeResource::class;
}
