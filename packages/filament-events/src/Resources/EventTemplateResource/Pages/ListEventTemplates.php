<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTemplateResource\Pages;

use AIArmada\FilamentEvents\Resources\EventTemplateResource;
use Filament\Resources\Pages\ListRecords;

final class ListEventTemplates extends ListRecords
{
    protected static string $resource = EventTemplateResource::class;
}
