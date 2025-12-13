<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip;

use AIArmada\FilamentChip\Pages\AnalyticsDashboardPage;
use AIArmada\FilamentChip\Pages\BulkPayoutPage;
use AIArmada\FilamentChip\Pages\FinancialOverviewPage;
use AIArmada\FilamentChip\Pages\PayoutDashboardPage;
use AIArmada\FilamentChip\Pages\RefundCenterPage;
use AIArmada\FilamentChip\Pages\WebhookConfigPage;
use AIArmada\FilamentChip\Pages\WebhookMonitorPage;
use AIArmada\FilamentChip\Resources\BankAccountResource;
use AIArmada\FilamentChip\Resources\ClientResource;
use AIArmada\FilamentChip\Resources\CompanyStatementResource;
use AIArmada\FilamentChip\Resources\PaymentResource;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource;
use AIArmada\FilamentChip\Resources\SendInstructionResource;
use AIArmada\FilamentChip\Widgets\AccountBalanceWidget;
use AIArmada\FilamentChip\Widgets\AccountTurnoverWidget;
use AIArmada\FilamentChip\Widgets\BankAccountStatusWidget;
use AIArmada\FilamentChip\Widgets\ChipStatsWidget;
use AIArmada\FilamentChip\Widgets\PaymentMethodsWidget;
use AIArmada\FilamentChip\Widgets\PayoutAmountWidget;
use AIArmada\FilamentChip\Widgets\PayoutStatsWidget;
use AIArmada\FilamentChip\Widgets\RecentPayoutsWidget;
use AIArmada\FilamentChip\Widgets\RecentTransactionsWidget;
use AIArmada\FilamentChip\Widgets\RecurringStatsWidget;
use AIArmada\FilamentChip\Widgets\RevenueChartWidget;
use AIArmada\FilamentChip\Widgets\TokenStatsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentChipPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-chip';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                AnalyticsDashboardPage::class,
                PayoutDashboardPage::class,
                FinancialOverviewPage::class,
                WebhookMonitorPage::class,
                WebhookConfigPage::class,
                RefundCenterPage::class,
                BulkPayoutPage::class,
            ])
            ->resources([
                PurchaseResource::class,
                PaymentResource::class,
                ClientResource::class,
                RecurringScheduleResource::class,
                SendInstructionResource::class,
                BankAccountResource::class,
                CompanyStatementResource::class,
            ])
            ->widgets([
                ChipStatsWidget::class,
                RevenueChartWidget::class,
                PaymentMethodsWidget::class,
                RecurringStatsWidget::class,
                RecentTransactionsWidget::class,
                PayoutStatsWidget::class,
                PayoutAmountWidget::class,
                BankAccountStatusWidget::class,
                RecentPayoutsWidget::class,
                AccountBalanceWidget::class,
                AccountTurnoverWidget::class,
                TokenStatsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
