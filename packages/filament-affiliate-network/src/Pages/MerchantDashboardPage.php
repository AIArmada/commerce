<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Pages;

use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

final class MerchantDashboardPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Merchant Dashboard';

    protected static ?string $title = 'Merchant Dashboard';

    protected static ?string $slug = 'affiliate-network/merchant-dashboard';

    protected string $view = 'filament-affiliate-network::pages.merchant-dashboard';

    public static function canAccess(): bool
    {
        return parent::canAccess() && NetworkAdminAccess::allows();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group');
    }

    public static function getNavigationSort(): int
    {
        return config('filament-affiliate-network.navigation.sort', 50) - 1;
    }

    public function getTitle(): string
    {
        return 'Merchant Dashboard';
    }

    protected function getHeaderWidgets(): array
    {
        return [];
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
            ->where('status', ApplicationStatus::Pending)
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
            ->with('site')
            ->where('status', OfferStatus::Published)
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
            ->where('status', OfferStatus::Published)
            ->count();
    }

    public function getPendingApplicationsCount(): int
    {
        return AffiliateOfferApplication::query()
            ->where('status', ApplicationStatus::Pending)
            ->count();
    }
}
