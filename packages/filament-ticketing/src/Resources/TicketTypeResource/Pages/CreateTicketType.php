<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources\TicketTypeResource\Pages;

use AIArmada\FilamentTicketing\Resources\TicketTypeResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTicketType extends CreateRecord
{
    protected static string $resource = TicketTypeResource::class;
}
