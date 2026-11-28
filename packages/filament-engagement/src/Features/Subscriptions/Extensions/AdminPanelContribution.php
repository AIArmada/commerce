<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Subscriptions\Extensions;

use AIArmada\FilamentEngagement\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentEngagement\Features\Subscriptions\EntryPoints\Filament\Resources\SubscriptionResource;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return array_filter([
            5 => config('filament-engagement.resources.enabled.subscription', true) ? SubscriptionResource::class : null,
        ], static fn (?string $class): bool => $class !== null);
    }

    public function widgets(): array
    {
        return [];
    }
}
