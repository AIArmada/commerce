<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers;

use AIArmada\FilamentVouchers\Pages\ABTestDashboard;
use AIArmada\FilamentVouchers\Pages\FraudConfigurationPage;
use AIArmada\FilamentVouchers\Pages\StackingConfigurationPage;
use AIArmada\FilamentVouchers\Pages\TargetingConfigurationPage;
use AIArmada\FilamentVouchers\Resources\CampaignResource;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource;
use AIArmada\FilamentVouchers\Resources\GiftCardResource;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource;
use AIArmada\FilamentVouchers\Widgets\AIInsightsWidget;
use AIArmada\FilamentVouchers\Widgets\CampaignStatsWidget;
use AIArmada\FilamentVouchers\Widgets\FraudStatsWidget;
use AIArmada\FilamentVouchers\Widgets\GiftCardStatsWidget;
use AIArmada\FilamentVouchers\Widgets\RedemptionTrendChart;
use AIArmada\FilamentVouchers\Widgets\VoucherStatsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentVouchersPlugin implements Plugin
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
        return 'filament-vouchers';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                CampaignResource::class,
                VoucherResource::class,
                VoucherUsageResource::class,
                GiftCardResource::class,
                VoucherWalletResource::class,
                FraudSignalResource::class,
            ])
            ->pages([
                ABTestDashboard::class,
                StackingConfigurationPage::class,
                TargetingConfigurationPage::class,
                FraudConfigurationPage::class,
            ])
            ->widgets([
                VoucherStatsWidget::class,
                CampaignStatsWidget::class,
                RedemptionTrendChart::class,
                GiftCardStatsWidget::class,
                FraudStatsWidget::class,
                AIInsightsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // No-op: the service provider handles runtime integration hooks.
    }
}
