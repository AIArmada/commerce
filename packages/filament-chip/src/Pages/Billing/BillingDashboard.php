<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages\Billing;

use AIArmada\CashierChip\CashierChip;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class BillingDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament-chip::pages.billing.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Billing Dashboard');
    }

    public function getViewData(): array
    {
        $billable = $this->getBillable();

        return [
            'billable' => $billable,
            'subscriptions' => $billable?->subscriptions()->active()->get() ?? collect(),
            'paymentMethods' => $this->getPaymentMethods(),
            'defaultPaymentMethod' => $billable?->defaultPaymentMethod(),
            'invoices' => $this->getRecentInvoices(),
        ];
    }

    protected function getBillable(): mixed
    {
        $user = filament()->auth()->user();

        if (! $user) {
            return null;
        }

        $billableModel = config('filament-chip.billing.billable_model');

        if ($billableModel && $user instanceof $billableModel) {
            return $user;
        }

        if (method_exists($user, 'currentTeam')) {
            return $user->currentTeam;
        }

        return $user;
    }

    /**
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    protected function getPaymentMethods(): \Illuminate\Support\Collection
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'paymentMethods')) {
            return collect();
        }

        return $billable->paymentMethods();
    }

    /**
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    protected function getRecentInvoices(): \Illuminate\Support\Collection
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'invoices')) {
            return collect();
        }

        return $billable->invoices(false)->take(5);
    }

    public function formatAmount(int $amount): string
    {
        return CashierChip::formatAmount($amount);
    }
}
