<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;
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
use Filament\Panel;

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
    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            AffiliateResource::class,
            AffiliateConversionResource::class,
            AffiliatePayoutResource::class,
            AffiliateProgramResource::class,
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

    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            AffiliateResource::class,
            AffiliateConversionResource::class,
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

it('boot method does not throw exceptions', function (): void {
    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    $plugin = new FilamentAffiliatesPlugin;

    // Boot should execute without throwing any exceptions
    // @phpstan-ignore argument.type
    $plugin->boot($panel);

    expect(true)->toBeTrue();
});
