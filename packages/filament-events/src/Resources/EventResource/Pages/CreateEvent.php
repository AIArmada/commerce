<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\Pages;

use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;
}
