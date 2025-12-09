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

it('mrr widget extends stats overview widget', function (): void {
    expect(is_subclass_of(MRRWidget::class, StatsOverviewWidget::class))->toBeTrue();
});

it('active subscribers widget extends stats overview widget', function (): void {
    expect(is_subclass_of(ActiveSubscribersWidget::class, StatsOverviewWidget::class))->toBeTrue();
});

it('churn rate widget extends stats overview widget', function (): void {
    expect(is_subclass_of(ChurnRateWidget::class, StatsOverviewWidget::class))->toBeTrue();
});

it('revenue chart widget extends chart widget', function (): void {
    expect(is_subclass_of(RevenueChartWidget::class, ChartWidget::class))->toBeTrue();
});

it('subscription distribution widget extends chart widget', function (): void {
    expect(is_subclass_of(SubscriptionDistributionWidget::class, ChartWidget::class))->toBeTrue();
});

it('trial conversions widget extends stats overview widget', function (): void {
    expect(is_subclass_of(TrialConversionsWidget::class, StatsOverviewWidget::class))->toBeTrue();
});

it('mrr widget has sort property', function (): void {
    $reflection = new ReflectionClass(MRRWidget::class);

    expect($reflection->hasProperty('sort'))->toBeTrue();
});

it('active subscribers widget has sort property', function (): void {
    $reflection = new ReflectionClass(ActiveSubscribersWidget::class);

    expect($reflection->hasProperty('sort'))->toBeTrue();
});

it('churn rate widget has sort property', function (): void {
    $reflection = new ReflectionClass(ChurnRateWidget::class);

    expect($reflection->hasProperty('sort'))->toBeTrue();
});

it('revenue chart widget has sort property', function (): void {
    $reflection = new ReflectionClass(RevenueChartWidget::class);

    expect($reflection->hasProperty('sort'))->toBeTrue();
});

it('subscription distribution widget has sort property', function (): void {
    $reflection = new ReflectionClass(SubscriptionDistributionWidget::class);

    expect($reflection->hasProperty('sort'))->toBeTrue();
});

it('trial conversions widget has sort property', function (): void {
    $reflection = new ReflectionClass(TrialConversionsWidget::class);

    expect($reflection->hasProperty('sort'))->toBeTrue();
});
