<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Portal\Queries;

use AIArmada\FilamentAffiliates\Features\Portal\Api\PortalContribution;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalConversions;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalCreatives;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalDashboard;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalDownlines;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalLinks;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalPayouts;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalProfile;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalPrograms;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalRegistration;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalSupport;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalVouchers;

final class PortalPanelContribution implements PortalContribution
{
    public function registration(): string
    {
        return PortalRegistration::class;
    }

    /** @return list<class-string> */
    public function pages(array $features): array
    {
        $pages = [];

        if ($features['dashboard'] ?? true) {
            $pages[] = PortalDashboard::class;
        }

        if ($features['profile'] ?? true) {
            $pages[] = PortalProfile::class;
        }

        if ($features['links'] ?? true) {
            $pages[] = PortalLinks::class;
            if ($features['creatives'] ?? true) {
                $pages[] = PortalCreatives::class;
            }
            if ($features['vouchers'] ?? true) {
                $pages[] = PortalVouchers::class;
            }
        }

        if ($features['programs'] ?? true) {
            $pages[] = PortalPrograms::class;
        }

        if ($features['downlines'] ?? true) {
            $pages[] = PortalDownlines::class;
        }

        if ($features['conversions'] ?? true) {
            $pages[] = PortalConversions::class;
        }

        if ($features['payouts'] ?? true) {
            $pages[] = PortalPayouts::class;
        }

        if ($features['support_compliance'] ?? true) {
            $pages[] = PortalSupport::class;
        }

        return $pages;
    }
}
