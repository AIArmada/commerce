<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateTouchpointResource;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Pages\ManageAffiliateBonusSettings;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Pages\ManageAffiliateCommissionSettings;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Pages\PerformanceBonusesPage;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateVolumeTierResource;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateSupportTicketResource;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateTaxDocumentResource;
use AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Pages\FraudReviewPage;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Widgets\FraudAlertWidget;
use AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Pages\ManageAffiliatePayoutSettings;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Widgets\PayoutQueueWidget;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateCreativeResource;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource;
use AIArmada\FilamentAffiliates\Features\Reporting\EntryPoints\Filament\Pages\ReportsPage;
use AIArmada\FilamentAffiliates\Features\Reporting\EntryPoints\Filament\Widgets\AffiliateStatsWidget;
use AIArmada\FilamentAffiliates\Features\Reporting\EntryPoints\Filament\Widgets\PerformanceOverviewWidget;
use AIArmada\FilamentAffiliates\Features\Reporting\EntryPoints\Filament\Widgets\RealTimeActivityWidget;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankHistoryResource;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankResource;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateUplineResource;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Widgets\UplineVisualizationWidget;
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;
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
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
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
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
                ManageAffiliateCommissionSettings::class,
                FraudReviewPage::class,
                PayoutBatchPage::class,
                ManageAffiliatePayoutSettings::class,
                PerformanceBonusesPage::class,
                ManageAffiliateBonusSettings::class,
                ReportsPage::class,
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
                AffiliateStatsWidget::class,
                PerformanceOverviewWidget::class,
                RealTimeActivityWidget::class,
                FraudAlertWidget::class,
                PayoutQueueWidget::class,
                UplineVisualizationWidget::class,
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
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
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
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
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
                ManageAffiliateCommissionSettings::class,
                FraudReviewPage::class,
                ReportsPage::class,
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
                AffiliateStatsWidget::class,
                PerformanceOverviewWidget::class,
                RealTimeActivityWidget::class,
                FraudAlertWidget::class,
                UplineVisualizationWidget::class,
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
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
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
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
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
                ManageAffiliateCommissionSettings::class,
                FraudReviewPage::class,
                PayoutBatchPage::class,
                ManageAffiliatePayoutSettings::class,
                PerformanceBonusesPage::class,
                ManageAffiliateBonusSettings::class,
                ReportsPage::class,
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
                AffiliateStatsWidget::class,
                PerformanceOverviewWidget::class,
                RealTimeActivityWidget::class,
                FraudAlertWidget::class,
                PayoutQueueWidget::class,
                UplineVisualizationWidget::class,
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
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
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
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
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
                ManageAffiliateCommissionSettings::class,
                FraudReviewPage::class,
                PayoutBatchPage::class,
                ManageAffiliatePayoutSettings::class,
                PerformanceBonusesPage::class,
                ReportsPage::class,
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
                AffiliateStatsWidget::class,
                PerformanceOverviewWidget::class,
                RealTimeActivityWidget::class,
                FraudAlertWidget::class,
                PayoutQueueWidget::class,
                UplineVisualizationWidget::class,
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
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
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
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
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
                ManageAffiliateCommissionSettings::class,
                FraudReviewPage::class,
                PayoutBatchPage::class,
                ManageAffiliatePayoutSettings::class,
                PerformanceBonusesPage::class,
                ManageAffiliateBonusSettings::class,
                ReportsPage::class,
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with(Mockery::on(static function (array $actual): bool {
            $expected = [
                AffiliateStatsWidget::class,
                PerformanceOverviewWidget::class,
                RealTimeActivityWidget::class,
                FraudAlertWidget::class,
                PayoutQueueWidget::class,
                UplineVisualizationWidget::class,
            ];
            sort($actual);
            sort($expected);

            return $actual === $expected;
        }))
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentAffiliatesPlugin)->register($panel);
});
