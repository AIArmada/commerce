<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\FilamentAffiliates\Concerns\InteractsWithAffiliate;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class PortalDashboard extends Page
{
    use InteractsWithAffiliate;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -2;

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

        return [
            'affiliate' => $affiliate,
            'hasAffiliate' => $this->hasAffiliate(),
            'totalEarnings' => $this->getTotalEarnings(),
            'pendingEarnings' => $this->getPendingEarnings(),
            'totalClicks' => $this->getTotalClicks(),
            'totalConversions' => $this->getTotalConversions(),
            'recentConversions' => $this->getConversions(5),
            'recentPayouts' => $this->getPayouts(3),
        ];
    }
}
