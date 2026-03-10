<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Widgets\EventTrendWidget;
use AIArmada\FilamentSignals\Widgets\PendingSignalAlertsWidget;
use AIArmada\FilamentSignals\Widgets\SignalsStatsWidget;
use BackedEnum;
use Filament\Pages\Dashboard;

final class SignalsDashboard extends Dashboard
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $title = 'Signals Dashboard';

    protected static ?string $slug = 'signals';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-signals.navigation_group', 'Insights');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.dashboard', 10);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.dashboard', true);
    }

    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }

    public function getWidgets(): array
    {
        $widgets = [];

        if (config('filament-signals.features.widgets', true)) {
            $widgets[] = SignalsStatsWidget::class;
        }

        if (config('filament-signals.features.trend_chart', true)) {
            $widgets[] = EventTrendWidget::class;
        }

        if (config('filament-signals.features.pending_alerts_widget', true)) {
            $widgets[] = PendingSignalAlertsWidget::class;
        }

        return $widgets;
    }
}
