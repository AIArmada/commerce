<?php

declare(strict_types=1);

use AIArmada\FilamentVouchers\FilamentVouchersPlugin;
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
use Filament\Panel;
use Mockery;

it('exposes a stable plugin id', function (): void {
    expect((new FilamentVouchersPlugin())->getId())->toBe('filament-vouchers');
});

it('registers voucher resources, pages, and widgets', function (): void {
    /** @var Panel&\Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            CampaignResource::class,
            VoucherResource::class,
            VoucherUsageResource::class,
            GiftCardResource::class,
            VoucherWalletResource::class,
            FraudSignalResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
            ABTestDashboard::class,
            StackingConfigurationPage::class,
            TargetingConfigurationPage::class,
            FraudConfigurationPage::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([
            VoucherStatsWidget::class,
            CampaignStatsWidget::class,
            RedemptionTrendChart::class,
            GiftCardStatsWidget::class,
            FraudStatsWidget::class,
            AIInsightsWidget::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentVouchersPlugin())->register($panel);
});
