<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTaxonomyResource\Pages;

use AIArmada\FilamentEvents\Resources\EventTaxonomyResource;
use Filament\Resources\Pages\EditRecord;

final class EditEventTaxonomy extends EditRecord
{
    protected static string $resource = EventTaxonomyResource::class;
}
