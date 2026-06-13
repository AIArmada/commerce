<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventRegistrationParticipantResource\Pages;

use AIArmada\FilamentEvents\Resources\EventRegistrationParticipantResource;
use Filament\Resources\Pages\ListRecords;

final class ListEventRegistrationParticipants extends ListRecords
{
    protected static string $resource = EventRegistrationParticipantResource::class;
}
