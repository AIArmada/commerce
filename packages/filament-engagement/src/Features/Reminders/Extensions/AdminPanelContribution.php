<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Reminders\Extensions;

use AIArmada\FilamentEngagement\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentEngagement\Features\Reminders\EntryPoints\Filament\Resources\ReminderResource;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return array_filter([
            6 => config('filament-engagement.resources.enabled.reminder', true) ? ReminderResource::class : null,
        ], static fn (?string $class): bool => $class !== null);
    }

    public function widgets(): array
    {
        return [];
    }
}
