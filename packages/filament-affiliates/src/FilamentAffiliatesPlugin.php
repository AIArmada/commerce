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
            ->resources($this->getResources())
            ->pages($this->getPages())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * @return array<class-string>
     */
    private function getResources(): array
    {
        $features = $this->getAdminFeatures();
        $resources = [AffiliateResource::class];

        if ($features['conversions']) {
            $resources[] = AffiliateConversionResource::class;
        }

        if ($features['payouts']) {
            $resources[] = AffiliatePayoutResource::class;
        }

        if ($features['programs']) {
            $resources[] = AffiliateProgramResource::class;
        }

        if ($features['fraud_monitoring']) {
            $resources[] = AffiliateFraudSignalResource::class;
        }

        return $resources;
    }

    /**
     * @return array<class-string>
     */
    private function getPages(): array
    {
        $features = $this->getAdminFeatures();
        $pages = [];

        if ($features['fraud_monitoring']) {
            $pages[] = FraudReviewPage::class;
        }

        if ($features['payouts']) {
            $pages[] = PayoutBatchPage::class;
        }

        if ($features['reports']) {
            $pages[] = ReportsPage::class;
        }

        return $pages;
    }

    /**
     * @return array<class-string>
     */
    private function getWidgets(): array
    {
        $features = $this->getAdminFeatures();
        $widgets = [
            AffiliateStatsWidget::class,
            PerformanceOverviewWidget::class,
            RealTimeActivityWidget::class,
        ];

        if ($features['fraud_monitoring']) {
            $widgets[] = FraudAlertWidget::class;
        }

        if ($features['payouts']) {
            $widgets[] = PayoutQueueWidget::class;
        }

        if ($features['network_visualization']) {
            $widgets[] = NetworkVisualizationWidget::class;
        }

        return $widgets;
    }

    /**
     * @return array{conversions: bool, payouts: bool, programs: bool, fraud_monitoring: bool, reports: bool, network_visualization: bool}
     */
    private function getAdminFeatures(): array
    {
        $configured = config('filament-affiliates.features.admin', []);
        $commissionTrackingEnabled = (bool) config('affiliates.features.commission_tracking.enabled', true);

        return [
            'conversions' => (bool) ($configured['conversions'] ?? true),
            'payouts' => $commissionTrackingEnabled && (bool) ($configured['payouts'] ?? true),
            'programs' => $commissionTrackingEnabled && (bool) ($configured['programs'] ?? true),
            'fraud_monitoring' => (bool) ($configured['fraud_monitoring'] ?? true),
            'reports' => (bool) ($configured['reports'] ?? true),
            'network_visualization' => (bool) ($configured['network_visualization'] ?? true),
        ];
    }
}
