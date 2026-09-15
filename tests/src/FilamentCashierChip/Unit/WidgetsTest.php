<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\Widgets\ActiveSubscribersWidget;
use AIArmada\FilamentCashierChip\Widgets\ChurnRateWidget;
use AIArmada\FilamentCashierChip\Widgets\MRRWidget;
use AIArmada\FilamentCashierChip\Widgets\RevenueChartWidget;
use AIArmada\FilamentCashierChip\Widgets\SubscriptionDistributionWidget;
use AIArmada\FilamentCashierChip\Widgets\TrialConversionsWidget;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;

it('widgets extend the expected base classes and define sort order', function (): void {
    $statsWidgets = [MRRWidget::class, ActiveSubscribersWidget::class, ChurnRateWidget::class, TrialConversionsWidget::class];
    $chartWidgets = [RevenueChartWidget::class, SubscriptionDistributionWidget::class];

    foreach ($statsWidgets as $widget) {
        expect(is_subclass_of($widget, StatsOverviewWidget::class))->toBeTrue();
    }

    foreach ($chartWidgets as $widget) {
        expect(is_subclass_of($widget, ChartWidget::class))->toBeTrue();
    }

    foreach (array_merge($statsWidgets, $chartWidgets) as $widget) {
        expect((new ReflectionClass($widget))->hasProperty('sort'))->toBeTrue();
    }
});
