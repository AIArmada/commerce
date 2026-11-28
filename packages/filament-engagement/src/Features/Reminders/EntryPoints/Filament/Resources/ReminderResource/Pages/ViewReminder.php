<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Reminders\EntryPoints\Filament\Resources\ReminderResource\Pages;

use AIArmada\FilamentEngagement\Features\Reminders\EntryPoints\Filament\Resources\ReminderResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewReminder extends ViewRecord
{
    protected static string $resource = ReminderResource::class;
}
