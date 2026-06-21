<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources\CommunicationResource\Pages;

use AIArmada\Filament\Communications\Resources\CommunicationResource;
use Filament\Resources\Pages\ListRecords;

final class ListCommunications extends ListRecords
{
    protected static string $resource = CommunicationResource::class;
}
