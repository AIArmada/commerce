<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\TitleResource\Pages;

use AIArmada\FilamentPersons\Resources\TitleResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTitle extends CreateRecord
{
    protected static string $resource = TitleResource::class;
}
