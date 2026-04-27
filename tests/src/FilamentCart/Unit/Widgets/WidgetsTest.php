<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Widgets\AbandonedCartsWidget;
use AIArmada\FilamentCart\Widgets\CartStatsOverviewWidget;
use AIArmada\FilamentCart\Widgets\CartStatsWidget;
use AIArmada\FilamentCart\Widgets\LiveStatsWidget;
use AIArmada\FilamentCart\Widgets\RecentActivityWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Widget;

describe('Widgets Instantiation', function (): void {
    it('can instantiate AbandonedCartsWidget', function (): void {
        $widget = new AbandonedCartsWidget;
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate CartStatsOverviewWidget', function (): void {
        $widget = new CartStatsOverviewWidget;
        expect($widget)->toBeInstanceOf(StatsOverviewWidget::class);
    });

    it('can instantiate CartStatsWidget', function (): void {
        $widget = new CartStatsWidget;
        expect($widget)->toBeInstanceOf(StatsOverviewWidget::class);
    });

    it('can instantiate LiveStatsWidget', function (): void {
        $widget = new LiveStatsWidget;
        expect($widget)->toBeInstanceOf(StatsOverviewWidget::class);
    });

    it('can instantiate RecentActivityWidget', function (): void {
        $widget = new RecentActivityWidget;
        expect($widget)->toBeInstanceOf(Widget::class);
    });
});
