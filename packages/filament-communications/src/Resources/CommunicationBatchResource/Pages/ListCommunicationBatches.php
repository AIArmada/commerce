<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources\CommunicationBatchResource\Pages;

use AIArmada\Filament\Communications\Resources\CommunicationBatchResource;
use Filament\Resources\Pages\ListRecords;

final class ListCommunicationBatches extends ListRecords
{
    protected static string $resource = CommunicationBatchResource::class;
}
