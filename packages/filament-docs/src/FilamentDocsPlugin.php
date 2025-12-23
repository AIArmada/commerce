<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs;

use AIArmada\FilamentDocs\Pages\AgingReportPage;
use AIArmada\FilamentDocs\Pages\PendingApprovalsPage;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use AIArmada\FilamentDocs\Widgets\DocStatsWidget;
use AIArmada\FilamentDocs\Widgets\QuickActionsWidget;
use AIArmada\FilamentDocs\Widgets\RecentDocumentsWidget;
use AIArmada\FilamentDocs\Widgets\RevenueChartWidget;
use AIArmada\FilamentDocs\Widgets\StatusBreakdownWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentDocsPlugin implements Plugin
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
        return 'filament-docs';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                DocResource::class,
                DocTemplateResource::class,
                DocSequenceResource::class,
                DocEmailTemplateResource::class,
            ])
            ->pages([
                AgingReportPage::class,
                PendingApprovalsPage::class,
            ])
            ->widgets([
                QuickActionsWidget::class,
                RecentDocumentsWidget::class,
                StatusBreakdownWidget::class,
                RevenueChartWidget::class,
                DocStatsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
