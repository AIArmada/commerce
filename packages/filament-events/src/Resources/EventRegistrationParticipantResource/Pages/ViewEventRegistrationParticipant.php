<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventRegistrationParticipantResource\Pages;

use AIArmada\FilamentEvents\Resources\EventRegistrationParticipantResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewEventRegistrationParticipant extends ViewRecord
{
    protected static string $resource = EventRegistrationParticipantResource::class;
}
