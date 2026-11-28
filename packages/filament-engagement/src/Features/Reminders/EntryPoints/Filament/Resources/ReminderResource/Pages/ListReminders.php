<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Reminders\EntryPoints\Filament\Resources\ReminderResource\Pages;

use AIArmada\FilamentEngagement\Features\Reminders\EntryPoints\Filament\Resources\ReminderResource;
use Filament\Resources\Pages\ListRecords;

final class ListReminders extends ListRecords
{
    protected static string $resource = ReminderResource::class;
}
