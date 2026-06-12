<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventRegistrationResource\Pages;

use AIArmada\FilamentEvents\Resources\EventRegistrationResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewEventRegistration extends ViewRecord
{
    protected static string $resource = EventRegistrationResource::class;
}
