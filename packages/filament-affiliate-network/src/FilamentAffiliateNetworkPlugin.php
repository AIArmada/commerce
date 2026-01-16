<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork;

use AIArmada\FilamentAffiliateNetwork\Pages\AffiliateMarketplacePage;
use AIArmada\FilamentAffiliateNetwork\Pages\MerchantDashboardPage;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget;
use AIArmada\FilamentAffiliateNetwork\Widgets\TopOffersWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentAffiliateNetworkPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-affiliate-network';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                AffiliateSiteResource::class,
                AffiliateOfferResource::class,
                AffiliateOfferCategoryResource::class,
                AffiliateOfferApplicationResource::class,
            ])
            ->pages([
                MerchantDashboardPage::class,
                AffiliateMarketplacePage::class,
            ])
            ->widgets([
                NetworkStatsWidget::class,
                TopOffersWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
