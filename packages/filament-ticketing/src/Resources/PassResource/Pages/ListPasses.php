<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources\PassResource\Pages;

use AIArmada\FilamentTicketing\Resources\PassResource;
use Filament\Resources\Pages\ListRecords;

final class ListPasses extends ListRecords
{
    protected static string $resource = PassResource::class;
}
