<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTemplateResource\Pages;

use AIArmada\FilamentEvents\Resources\EventTemplateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEventTemplate extends CreateRecord
{
    protected static string $resource = EventTemplateResource::class;
}
