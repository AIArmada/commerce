<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Pages;

use AIArmada\FilamentShipping\Widgets\CarrierPerformanceWidget;
use AIArmada\FilamentShipping\Widgets\PendingActionsWidget;
use AIArmada\FilamentShipping\Widgets\PendingShipmentsWidget;
use AIArmada\FilamentShipping\Widgets\ShippingDashboardWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ShippingDashboard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    /** @var view-string */
    protected string $view = 'filament-shipping::pages.shipping-dashboard';

    protected static ?string $slug = 'shipping-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-shipping.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-shipping.pages.navigation_sort.dashboard');
    }

    public function getTitle(): string
    {
        return 'Shipping Dashboard';
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 5;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ShippingDashboardWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            CarrierPerformanceWidget::class,
            PendingShipmentsWidget::class,
            PendingActionsWidget::class,
        ];
    }
}
