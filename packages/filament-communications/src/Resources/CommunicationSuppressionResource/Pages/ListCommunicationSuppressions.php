<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources\CommunicationSuppressionResource\Pages;

use AIArmada\Filament\Communications\Resources\CommunicationSuppressionResource;
use Filament\Resources\Pages\ListRecords;

final class ListCommunicationSuppressions extends ListRecords
{
    protected static string $resource = CommunicationSuppressionResource::class;
}
