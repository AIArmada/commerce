<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;
use AIArmada\FilamentAffiliates\Pages\FraudReviewPage;
use AIArmada\FilamentAffiliates\Pages\ManageAffiliateBonusSettings;
use AIArmada\FilamentAffiliates\Pages\ManageAffiliateCommissionSettings;
use AIArmada\FilamentAffiliates\Pages\ManageAffiliatePayoutSettings;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Pages\PerformanceBonusesPage;
use AIArmada\FilamentAffiliates\Pages\ReportsPage;
use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateCreativeResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankHistoryResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateTouchpointResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateUplineResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateVolumeTierResource;
use AIArmada\FilamentAffiliates\Widgets\AffiliateStatsWidget;
use AIArmada\FilamentAffiliates\Widgets\FraudAlertWidget;
use AIArmada\FilamentAffiliates\Widgets\PayoutQueueWidget;
use AIArmada\FilamentAffiliates\Widgets\PerformanceOverviewWidget;
use AIArmada\FilamentAffiliates\Widgets\RealTimeActivityWidget;
use AIArmada\FilamentAffiliates\Widgets\UplineVisualizationWidget;
use Filament\Panel;
use Mockery\MockInterface;

it('can be created via make factory method', function (): void {
    $plugin = FilamentAffiliatesPlugin::make();

    expect($plugin)
        ->toBeInstanceOf(FilamentAffiliatesPlugin::class)
        ->and($plugin->getId())->toBe('filament-affiliates');
});

it('registers affiliate resources, pages, and widgets', function (): void {
    /** @var Panel&MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            AffiliateResource::class,
            AffiliateConversionResource::class,
            AffiliatePayoutResource::class,
            AffiliateProgramResource::class,
            AffiliateCommissionTemplateResource::class,
            AffiliateVolumeTierResource::class,
            AffiliateLinkResource::class,
            AffiliateTouchpointResource::class,
            AffiliateRankResource::class,
            AffiliateRankHistoryResource::class,
            AffiliateSupportTicketResource::class,
            AffiliateTaxDocumentResource::class,
            AffiliateUplineResource::class,
            AffiliateFraudSignalResource::class,
            AffiliateCreativeResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
            ManageAffiliateCommissionSettings::class,
            FraudReviewPage::class,
            PayoutBatchPage::class,
            ManageAffiliatePayoutSettings::class,
            PerformanceBonusesPage::class,
            ManageAffiliateBonusSettings::class,
            ReportsPage::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([
            AffiliateStatsWidget::class,
            PerformanceOverviewWidget::class,
            RealTimeActivityWidget::class,
            FraudAlertWidget::class,
            PayoutQueueWidget::class,
            UplineVisualizationWidget::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentAffiliatesPlugin)->register($panel);
});

it('skips payout and program admin surfaces when commission tracking is disabled', function (): void {
    config()->set('affiliates.features.commission_tracking.enabled', false);

    /** @var Panel&MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            AffiliateResource::class,
            AffiliateConversionResource::class,
            AffiliateLinkResource::class,
            AffiliateTouchpointResource::class,
            AffiliateRankResource::class,
            AffiliateRankHistoryResource::class,
            AffiliateSupportTicketResource::class,
            AffiliateTaxDocumentResource::class,
            AffiliateUplineResource::class,
            AffiliateFraudSignalResource::class,
            AffiliateCreativeResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
            ManageAffiliateCommissionSettings::class,
            FraudReviewPage::class,
            ReportsPage::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([
            AffiliateStatsWidget::class,
            PerformanceOverviewWidget::class,
            RealTimeActivityWidget::class,
            FraudAlertWidget::class,
            UplineVisualizationWidget::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentAffiliatesPlugin)->register($panel);
});

it('skips link admin surface when links feature is disabled', function (): void {
    config()->set('filament-affiliates.features.admin.links', false);

    /** @var Panel&MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            AffiliateResource::class,
            AffiliateConversionResource::class,
            AffiliatePayoutResource::class,
            AffiliateProgramResource::class,
            AffiliateCommissionTemplateResource::class,
            AffiliateVolumeTierResource::class,
            AffiliateTouchpointResource::class,
            AffiliateRankResource::class,
            AffiliateRankHistoryResource::class,
            AffiliateSupportTicketResource::class,
            AffiliateTaxDocumentResource::class,
            AffiliateUplineResource::class,
            AffiliateFraudSignalResource::class,
            AffiliateCreativeResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
            ManageAffiliateCommissionSettings::class,
            FraudReviewPage::class,
            PayoutBatchPage::class,
            ManageAffiliatePayoutSettings::class,
            PerformanceBonusesPage::class,
            ManageAffiliateBonusSettings::class,
            ReportsPage::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([
            AffiliateStatsWidget::class,
            PerformanceOverviewWidget::class,
            RealTimeActivityWidget::class,
            FraudAlertWidget::class,
            PayoutQueueWidget::class,
            UplineVisualizationWidget::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentAffiliatesPlugin)->register($panel);
});

it('skips commission template admin surface when commission management is disabled', function (): void {
    config()->set('filament-affiliates.features.admin.commission_management', false);

    /** @var Panel&MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            AffiliateResource::class,
            AffiliateConversionResource::class,
            AffiliatePayoutResource::class,
            AffiliateProgramResource::class,
            AffiliateVolumeTierResource::class,
            AffiliateLinkResource::class,
            AffiliateTouchpointResource::class,
            AffiliateRankResource::class,
            AffiliateRankHistoryResource::class,
            AffiliateSupportTicketResource::class,
            AffiliateTaxDocumentResource::class,
            AffiliateUplineResource::class,
            AffiliateFraudSignalResource::class,
            AffiliateCreativeResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
            ManageAffiliateCommissionSettings::class,
            FraudReviewPage::class,
            PayoutBatchPage::class,
            ManageAffiliatePayoutSettings::class,
            PerformanceBonusesPage::class,
            ReportsPage::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([
            AffiliateStatsWidget::class,
            PerformanceOverviewWidget::class,
            RealTimeActivityWidget::class,
            FraudAlertWidget::class,
            PayoutQueueWidget::class,
            UplineVisualizationWidget::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentAffiliatesPlugin)->register($panel);
});

it('hides volume tiers when the volume tier feature is disabled', function (): void {
    config()->set('filament-affiliates.features.admin.volume_tiers', false);

    $method = new ReflectionMethod(FilamentAffiliatesPlugin::class, 'getResources');
    $resources = $method->invoke(new FilamentAffiliatesPlugin);

    expect($resources)
        ->toContain(AffiliateCommissionTemplateResource::class)
        ->not->toContain(AffiliateVolumeTierResource::class);
});

it('skips support and compliance admin surfaces when support compliance is disabled', function (): void {
    config()->set('affiliates.features.commission_tracking.enabled', true);
    config()->set('filament-affiliates.features.admin', [
        'conversions' => true,
        'payouts' => true,
        'programs' => true,
        'commission_management' => true,
        'volume_tiers' => true,
        'links' => true,
        'attribution' => true,
        'ranks' => true,
        'support_compliance' => false,
        'fraud_monitoring' => true,
        'reports' => true,
        'upline_visualization' => true,
        'creatives' => true,
    ]);

    /** @var Panel&MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            AffiliateResource::class,
            AffiliateConversionResource::class,
            AffiliatePayoutResource::class,
            AffiliateProgramResource::class,
            AffiliateCommissionTemplateResource::class,
            AffiliateVolumeTierResource::class,
            AffiliateLinkResource::class,
            AffiliateTouchpointResource::class,
            AffiliateRankResource::class,
            AffiliateRankHistoryResource::class,
            AffiliateUplineResource::class,
            AffiliateFraudSignalResource::class,
            AffiliateCreativeResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
            ManageAffiliateCommissionSettings::class,
            FraudReviewPage::class,
            PayoutBatchPage::class,
            ManageAffiliatePayoutSettings::class,
            PerformanceBonusesPage::class,
            ManageAffiliateBonusSettings::class,
            ReportsPage::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([
            AffiliateStatsWidget::class,
            PerformanceOverviewWidget::class,
            RealTimeActivityWidget::class,
            FraudAlertWidget::class,
            PayoutQueueWidget::class,
            UplineVisualizationWidget::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentAffiliatesPlugin)->register($panel);
});
