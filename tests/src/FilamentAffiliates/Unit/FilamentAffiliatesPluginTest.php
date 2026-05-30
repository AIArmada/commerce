<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;
use AIArmada\FilamentAffiliates\Pages\FraudReviewPage;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Pages\ReportsPage;
use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateNetworkResource;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankHistoryResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateTouchpointResource;
use AIArmada\FilamentAffiliates\Widgets\AffiliateStatsWidget;
use AIArmada\FilamentAffiliates\Widgets\FraudAlertWidget;
use AIArmada\FilamentAffiliates\Widgets\NetworkVisualizationWidget;
use AIArmada\FilamentAffiliates\Widgets\PayoutQueueWidget;
use AIArmada\FilamentAffiliates\Widgets\PerformanceOverviewWidget;
use AIArmada\FilamentAffiliates\Widgets\RealTimeActivityWidget;
use Filament\Panel;
use Mockery\MockInterface;

it('exposes a stable plugin id', function (): void {
    expect((new FilamentAffiliatesPlugin)->getId())->toBe('filament-affiliates');
});

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
            AffiliateLinkResource::class,
            AffiliateTouchpointResource::class,
            AffiliateRankResource::class,
            AffiliateRankHistoryResource::class,
            AffiliateSupportTicketResource::class,
            AffiliateTaxDocumentResource::class,
            AffiliateNetworkResource::class,
            AffiliateFraudSignalResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
            FraudReviewPage::class,
            PayoutBatchPage::class,
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
            NetworkVisualizationWidget::class,
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
            AffiliateNetworkResource::class,
            AffiliateFraudSignalResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
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
            NetworkVisualizationWidget::class,
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
            AffiliateTouchpointResource::class,
            AffiliateRankResource::class,
            AffiliateRankHistoryResource::class,
            AffiliateSupportTicketResource::class,
            AffiliateTaxDocumentResource::class,
            AffiliateNetworkResource::class,
            AffiliateFraudSignalResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
            FraudReviewPage::class,
            PayoutBatchPage::class,
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
            NetworkVisualizationWidget::class,
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
            AffiliateLinkResource::class,
            AffiliateTouchpointResource::class,
            AffiliateRankResource::class,
            AffiliateRankHistoryResource::class,
            AffiliateSupportTicketResource::class,
            AffiliateTaxDocumentResource::class,
            AffiliateNetworkResource::class,
            AffiliateFraudSignalResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
            FraudReviewPage::class,
            PayoutBatchPage::class,
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
            NetworkVisualizationWidget::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentAffiliatesPlugin)->register($panel);
});

it('skips support and compliance admin surfaces when support compliance is disabled', function (): void {
    config()->set('filament-affiliates.features.admin.support_compliance', false);

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
            AffiliateLinkResource::class,
            AffiliateTouchpointResource::class,
            AffiliateRankResource::class,
            AffiliateRankHistoryResource::class,
            AffiliateNetworkResource::class,
            AffiliateFraudSignalResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
            FraudReviewPage::class,
            PayoutBatchPage::class,
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
            NetworkVisualizationWidget::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentAffiliatesPlugin)->register($panel);
});

it('boot method does not throw exceptions', function (): void {
    /** @var Panel&MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    $plugin = new FilamentAffiliatesPlugin;

    // Boot should execute without throwing any exceptions
    // @phpstan-ignore argument.type
    $plugin->boot($panel);

    expect(true)->toBeTrue();
});
