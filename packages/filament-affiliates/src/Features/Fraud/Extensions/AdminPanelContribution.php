<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Fraud\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Pages\FraudReviewPage;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Widgets\FraudAlertWidget;

final class AdminPanelContribution implements ContributionContract
{
    public function priority(): int
    {
        return 90;
    }

    public function resources(array $features): array
    {
        return array_values(array_filter([
            ($features['fraud_monitoring'] ?? false) ? AffiliateFraudSignalResource::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }

    public function pages(array $features): array
    {
        return array_values(array_filter([
            ($features['fraud_monitoring'] ?? false) ? FraudReviewPage::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }

    public function widgets(array $features): array
    {
        return array_values(array_filter([
            ($features['fraud_monitoring'] ?? false) ? FraudAlertWidget::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }
}
