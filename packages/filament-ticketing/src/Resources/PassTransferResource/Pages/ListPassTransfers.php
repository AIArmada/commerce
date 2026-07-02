<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources\PassTransferResource\Pages;

use AIArmada\FilamentTicketing\Resources\PassTransferResource;
use Filament\Resources\Pages\ListRecords;

final class ListPassTransfers extends ListRecords
{
    protected static string $resource = PassTransferResource::class;
}
