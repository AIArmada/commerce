<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources\CommunicationThreadResource\Pages;

use AIArmada\Filament\Communications\Resources\CommunicationThreadResource;
use Filament\Resources\Pages\ListRecords;

final class ListCommunicationThreads extends ListRecords
{
    protected static string $resource = CommunicationThreadResource::class;
}
