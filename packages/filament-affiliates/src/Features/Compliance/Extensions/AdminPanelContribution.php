<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Compliance\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateSupportTicketResource;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateTaxDocumentResource;

final class AdminPanelContribution implements ContributionContract
{
    public function priority(): int
    {
        return 80;
    }

    public function resources(array $features): array
    {
        return array_values(array_filter([
            ($features['support_compliance'] ?? false) ? AffiliateSupportTicketResource::class : null,
            ($features['support_compliance'] ?? false) ? AffiliateTaxDocumentResource::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }

    public function pages(array $features): array
    {
        return [];
    }

    public function widgets(array $features): array
    {
        return [];
    }
}
