<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Pages;

use AIArmada\FilamentCashier\FilamentCashierPlugin;
use AIArmada\FilamentCashier\Widgets\GatewayBreakdownWidget;
use AIArmada\FilamentCashier\Widgets\GatewayComparisonWidget;
use AIArmada\FilamentCashier\Widgets\TotalMrrWidget;
use AIArmada\FilamentCashier\Widgets\TotalSubscribersWidget;
use AIArmada\FilamentCashier\Widgets\UnifiedChurnWidget;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

final class BillingDashboard extends BaseDashboard
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 0;

    protected static string $routePath = 'billing-dashboard';

    protected static ?string $slug = 'billing-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::dashboard.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentCashierPlugin::get()->getNavigationGroup();
    }

    public function getTitle(): string
    {
        return __('filament-cashier::dashboard.title');
    }

    /**
     * @return array<string, int>|int
     */
    public function getColumns(): int | array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 4,
        ];
    }

    public function getWidgets(): array
    {
        return [
            TotalMrrWidget::class,
            TotalSubscribersWidget::class,
            UnifiedChurnWidget::class,
            GatewayBreakdownWidget::class,
            GatewayComparisonWidget::class,
        ];
    }
}
