<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources\CommunicationTemplateResource\Pages;

use AIArmada\Filament\Communications\Resources\CommunicationTemplateResource;
use Filament\Resources\Pages\ListRecords;

final class ListCommunicationTemplates extends ListRecords
{
    protected static string $resource = CommunicationTemplateResource::class;
}
