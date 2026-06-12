<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventRegistrationResource\Pages;

use AIArmada\FilamentEvents\Resources\EventRegistrationResource;
use Filament\Resources\Pages\ListRecords;

final class ListEventRegistrations extends ListRecords
{
    protected static string $resource = EventRegistrationResource::class;
}
