<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages\Billing;

use AIArmada\FilamentChip\Concerns\InteractsWithBillable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class BillingDashboard extends Page
{
    use InteractsWithBillable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament-chip::pages.billing.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Billing Dashboard');
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $billable = $this->getBillable();

        return [
            'billable' => $billable,
            'subscriptions' => $this->getActiveSubscriptions(),
            'paymentMethods' => $this->getPaymentMethods(),
            'defaultPaymentMethod' => $this->getDefaultPaymentMethod(),
            'invoices' => $this->getRecentInvoices(),
        ];
    }

    public function formatAmount(int $amount): string
    {
        if (class_exists('\AIArmada\CashierChip\Cashier')) {
            return \AIArmada\CashierChip\Cashier::formatAmount($amount);
        }

        // Fallback formatting
        $currency = config('cashier-chip.currency', 'MYR');

        return $currency . ' ' . number_format($amount / 100, 2);
    }

    /**
     * @return Collection<int, mixed>
     */
    protected function getActiveSubscriptions(): Collection
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'subscriptions')) {
            return collect();
        }

        return $billable->subscriptions()->active()->get();
    }

    /**
     * @return Collection<int, mixed>
     */
    protected function getRecentInvoices(): Collection
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'invoices')) {
            return collect();
        }

        return $billable->invoices(false)->take(5);
    }
}
