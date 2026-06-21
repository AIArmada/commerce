<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources\CommunicationPreferenceResource\Pages;

use AIArmada\Filament\Communications\Resources\CommunicationPreferenceResource;
use Filament\Resources\Pages\ListRecords;

final class ListCommunicationPreferences extends ListRecords
{
    protected static string $resource = CommunicationPreferenceResource::class;
}
