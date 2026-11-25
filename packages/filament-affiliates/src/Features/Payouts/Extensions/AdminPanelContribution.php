<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Pages\ManageAffiliatePayoutSettings;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Widgets\PayoutQueueWidget;

final class AdminPanelContribution implements ContributionContract
{
    public function priority(): int
    {
        return 30;
    }

    public function resources(array $features): array
    {
        return array_values(array_filter([
            ($features['payouts'] ?? false) ? AffiliatePayoutResource::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }

    public function pages(array $features): array
    {
        return array_values(array_filter([
            ($features['payouts'] ?? false) ? PayoutBatchPage::class : null,
            ($features['payouts'] ?? false) ? ManageAffiliatePayoutSettings::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }

    public function widgets(array $features): array
    {
        return array_values(array_filter([
            ($features['payouts'] ?? false) ? PayoutQueueWidget::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }
}
