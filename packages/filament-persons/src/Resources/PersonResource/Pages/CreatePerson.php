<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\Pages;

use AIArmada\FilamentPersons\Resources\PersonResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePerson extends CreateRecord
{
    protected static string $resource = PersonResource::class;
}
