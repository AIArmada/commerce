<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Dashboard\EntryPoints\Filament\Pages;

use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\RecentApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Platform\NetworkAdminAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use UnitEnum;

final class MerchantDashboardPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Merchant Dashboard';

    protected static ?string $title = 'Merchant Dashboard';

    protected static ?string $slug = 'affiliate-network/merchant-dashboard';

    protected string $view = 'filament-affiliate-network::pages.merchant-dashboard';

    /** @var SupportCollection<int, RecentApplication>|null */
    private ?SupportCollection $memoizedRecentApplications = null;

    /** @var Collection<int, AffiliateOffer>|null */
    private ?Collection $memoizedTopOffers = null;

    /** @var array<string, int> */
    private array $memoizedCounts = [];

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
            Stat::make('Pending Lock Legs', number_format($this->getPendingLegsCount()))
                ->icon('heroicon-o-lock-closed')
                ->description('Awaiting lock maturity'),
            Stat::make('Issued Statements', number_format($this->getOpenStatementsCount()))
                ->icon('heroicon-o-document-currency-dollar')
                ->description('Issued but unpaid'),
        ];
    }

    /**
     * @return Collection<int, AffiliateOfferApplication>
     */
    public function getRecentApplications(): SupportCollection
    {
        return $this->memoizedRecentApplications ??= collect(app(EnrollmentApi::class)->recentPendingApplications());
    }

    /**
     * @return Collection<int, AffiliateOffer>
     */
    public function getTopOffers(): Collection
    {
        return $this->memoizedTopOffers ??= AffiliateOffer::query()
            ->with('site')
            ->where('status', OfferStatus::Published)
            ->withCount('applications')
            ->orderByDesc('applications_count')
            ->limit(5)
            ->get();
    }

    public function getSitesCount(): int
    {
        return $this->memoizedCounts['sites'] ??= AffiliateSite::query()->count();
    }

    public function getVerifiedSitesCount(): int
    {
        return $this->memoizedCounts['verified_sites'] ??= AffiliateSite::query()
            ->where('status', SiteStatus::Verified)
            ->count();
    }

    public function getActiveOffersCount(): int
    {
        return $this->memoizedCounts['active_offers'] ??= AffiliateOffer::query()
            ->where('status', OfferStatus::Published)
            ->count();
    }

    public function getPendingApplicationsCount(): int
    {
        return $this->memoizedCounts['pending_applications'] ??= AffiliateOfferApplication::query()
            ->where('status', ApplicationStatus::Pending)
            ->count();
    }

    public function getPendingLegsCount(): int
    {
        return $this->memoizedCounts['pending_legs'] ??= NetworkConversionLeg::query()
            ->where('status', LegStatus::Pending)
            ->count();
    }

    public function getOpenStatementsCount(): int
    {
        return $this->memoizedCounts['open_statements'] ??= MerchantStatement::query()
            ->where('status', SettlementStatus::Issued)
            ->count();
    }
}
