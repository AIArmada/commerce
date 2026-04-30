<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Pages;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget;
use AIArmada\FilamentAffiliateNetwork\Widgets\TopOffersWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

final class MerchantDashboardPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Merchant Dashboard';

    protected static ?string $title = 'Merchant Dashboard';

    protected static ?string $slug = 'affiliate-network/merchant-dashboard';

    protected string $view = 'filament-affiliate-network::pages.merchant-dashboard';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group', 'Affiliate Network');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50) - 1;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Merchant Dashboard';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            NetworkStatsWidget::class,
            TopOffersWidget::class,
        ];
    }

    /**
     * @return array<int, Stat>
     */
    public function getStats(): array
    {
        return [
            Stat::make('Sites', number_format($this->getSitesCount()))
                ->icon('heroicon-o-globe-alt')
                ->description('Total sites'),
            Stat::make('Verified Sites', number_format($this->getVerifiedSitesCount()))
                ->icon('heroicon-o-check-badge')
                ->description('Ready for affiliate traffic'),
            Stat::make('Active Offers', number_format($this->getActiveOffersCount()))
                ->icon('heroicon-o-gift')
                ->description('Currently running offers'),
            Stat::make('Pending Applications', number_format($this->getPendingApplicationsCount()))
                ->icon('heroicon-o-clock')
                ->description('Awaiting review'),
        ];
    }

    /**
     * @return Collection<int, AffiliateOfferApplication>
     */
    public function getRecentApplications(): Collection
    {
        return AffiliateOfferApplication::query()
            ->with(['offer', 'affiliate'])
            ->where('status', AffiliateOfferApplication::STATUS_PENDING)
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    /**
     * @return Collection<int, AffiliateOffer>
     */
    public function getTopOffers(): Collection
    {
        return AffiliateOffer::query()
            ->with(['site'])
            ->where('status', AffiliateOffer::STATUS_ACTIVE)
            ->withCount('applications')
            ->orderByDesc('applications_count')
            ->limit(5)
            ->get();
    }

    public function getSitesCount(): int
    {
        return AffiliateSite::query()->count();
    }

    public function getVerifiedSitesCount(): int
    {
        return AffiliateSite::query()
            ->where('status', AffiliateSite::STATUS_VERIFIED)
            ->count();
    }

    public function getActiveOffersCount(): int
    {
        return AffiliateOffer::query()
            ->where('status', AffiliateOffer::STATUS_ACTIVE)
            ->count();
    }

    public function getPendingApplicationsCount(): int
    {
        return AffiliateOfferApplication::query()
            ->where('status', AffiliateOfferApplication::STATUS_PENDING)
            ->count();
    }
}
