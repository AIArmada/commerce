<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Pages;

use AIArmada\FilamentCashier\CustomerPortal\Widgets\ActiveSubscriptionsWidget;
use AIArmada\FilamentCashier\CustomerPortal\Widgets\PaymentMethodsPreviewWidget;
use AIArmada\FilamentCashier\CustomerPortal\Widgets\RecentInvoicesWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

final class BillingOverview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament-cashier::customer-portal.billing-overview';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::portal.overview.title');
    }

    public function getTitle(): string
    {
        return __('filament-cashier::portal.overview.title');
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ActiveSubscriptionsWidget::class,
            PaymentMethodsPreviewWidget::class,
            RecentInvoicesWidget::class,
        ];
    }
}
