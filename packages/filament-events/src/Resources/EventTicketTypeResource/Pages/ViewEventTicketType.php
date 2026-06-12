<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTicketTypeResource\Pages;

use AIArmada\FilamentEvents\Resources\EventTicketTypeResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewEventTicketType extends ViewRecord
{
    protected static string $resource = EventTicketTypeResource::class;
}
