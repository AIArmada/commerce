<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CollectionResource\Pages;

use AIArmada\FilamentProducts\Resources\CollectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCollection extends CreateRecord
{
    protected static string $resource = CollectionResource::class;
}
