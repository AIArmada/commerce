<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates;

use AIArmada\FilamentAffiliates\Pages\FraudReviewPage;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Pages\ReportsPage;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use AIArmada\FilamentAffiliates\Widgets\AffiliateStatsWidget;
use AIArmada\FilamentAffiliates\Widgets\FraudAlertWidget;
use AIArmada\FilamentAffiliates\Widgets\NetworkVisualizationWidget;
use AIArmada\FilamentAffiliates\Widgets\PayoutQueueWidget;
use AIArmada\FilamentAffiliates\Widgets\PerformanceOverviewWidget;
use AIArmada\FilamentAffiliates\Widgets\RealTimeActivityWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentAffiliatesPlugin implements Plugin
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
        return 'filament-affiliates';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                AffiliateResource::class,
                AffiliateConversionResource::class,
                AffiliatePayoutResource::class,
                AffiliateProgramResource::class,
                AffiliateFraudSignalResource::class,
            ])
            ->pages([
                FraudReviewPage::class,
                PayoutBatchPage::class,
                ReportsPage::class,
            ])
            ->widgets([
                AffiliateStatsWidget::class,
                PerformanceOverviewWidget::class,
                RealTimeActivityWidget::class,
                FraudAlertWidget::class,
                PayoutQueueWidget::class,
                NetworkVisualizationWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
