<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class PortalDashboard extends PortalPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -2;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Affiliate Dashboard');
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $affiliate = $this->getAffiliate();
        $clicks = $this->getTotalClicks();
        $conversions = $this->getTotalConversions();

        return [
            'affiliate' => $affiliate,
            'hasAffiliate' => $this->hasAffiliate(),
            'totalEarnings' => $this->getTotalEarnings(),
            'pendingEarnings' => $this->getPendingEarnings(),
            'availableEarnings' => $this->getAvailableEarnings(),
            'totalClicks' => $clicks,
            'totalConversions' => $conversions,
            'conversionRate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 1) : 0,
            'recentConversions' => $this->getConversions(5),
            'recentPayouts' => $this->getPayouts(3),
            'vouchers' => $this->getVouchers(10),
            'downlines' => $this->getDownlines(),
        ];
    }
}
