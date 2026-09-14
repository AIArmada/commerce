<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTemplateResource\Pages;

use AIArmada\FilamentEvents\Resources\EventTemplateResource;
use Filament\Resources\Pages\EditRecord;

final class EditEventTemplate extends EditRecord
{
    protected static string $resource = EventTemplateResource::class;
}
