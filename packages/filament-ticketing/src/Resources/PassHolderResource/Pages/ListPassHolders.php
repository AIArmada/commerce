<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources\PassHolderResource\Pages;

use AIArmada\FilamentTicketing\Resources\PassHolderResource;
use Filament\Resources\Pages\ListRecords;

final class ListPassHolders extends ListRecords
{
    protected static string $resource = PassHolderResource::class;
}
