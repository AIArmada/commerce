<?php

declare(strict_types=1);

use AIArmada\FilamentDocs\FilamentDocsPlugin;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use AIArmada\FilamentDocs\Widgets\DocStatsWidget;
use AIArmada\FilamentDocs\Widgets\QuickActionsWidget;
use AIArmada\FilamentDocs\Widgets\RecentDocumentsWidget;
use AIArmada\FilamentDocs\Widgets\RevenueChartWidget;
use AIArmada\FilamentDocs\Widgets\StatusBreakdownWidget;
use Filament\Panel;

it('exposes a stable plugin id', function (): void {
    $plugin = new FilamentDocsPlugin;

    expect($plugin->getId())->toBe('filament-docs');
});

use AIArmada\FilamentDocs\Pages\AgingReportPage;
use AIArmada\FilamentDocs\Pages\PendingApprovalsPage;

it('registers docs resources and widgets on the panel', function (): void {
    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            DocResource::class,
            DocTemplateResource::class,
            DocSequenceResource::class,
            DocEmailTemplateResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([AgingReportPage::class, PendingApprovalsPage::class])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([
            QuickActionsWidget::class,
            RecentDocumentsWidget::class,
            StatusBreakdownWidget::class,
            RevenueChartWidget::class,
            DocStatsWidget::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentDocsPlugin)->register($panel);
});
