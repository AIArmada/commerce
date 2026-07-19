<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTaxonomyResource\Pages;

use AIArmada\FilamentEvents\Resources\EventTaxonomyResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEventTaxonomy extends CreateRecord
{
    protected static string $resource = EventTaxonomyResource::class;
}
