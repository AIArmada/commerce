<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Overview\Extensions;

use AIArmada\FilamentEngagement\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentEngagement\Features\Overview\EntryPoints\Filament\Widgets\EngagementOverviewWidget;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return [];
    }

    public function widgets(): array
    {
        return [EngagementOverviewWidget::class];
    }
}
