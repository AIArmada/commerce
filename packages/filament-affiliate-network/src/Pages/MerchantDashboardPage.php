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
use Illuminate\Contracts\Support\Htmlable;
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
