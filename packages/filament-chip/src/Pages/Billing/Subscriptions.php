<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages\Billing;

use AIArmada\FilamentChip\Concerns\InteractsWithBillable;
use BackedEnum;
use Exception;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class Subscriptions extends Page
{
    use InteractsWithBillable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament-chip::pages.billing.subscriptions';

    public static function getNavigationLabel(): string
    {
        return __('Subscriptions');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-chip.billing.features.subscriptions', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'billable' => $this->getBillable(),
            'subscriptions' => $this->getSubscriptions(),
            'cancelledSubscriptions' => $this->getCancelledSubscriptions(),
        ];
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'subscriptions')) {
            Notification::make()
                ->title(__('Subscription not found'))
                ->danger()
                ->send();

            return;
        }

        $subscription = $billable->subscriptions()->find($subscriptionId);

        if (! $subscription) {
            Notification::make()
                ->title(__('Subscription not found'))
                ->danger()
                ->send();

            return;
        }

        try {
            $subscription->cancel();

            Notification::make()
                ->title(__('Subscription cancelled'))
                ->body(__('Your subscription will remain active until the end of the billing period.'))
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title(__('Failed to cancel subscription'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resumeSubscription(string $subscriptionId): void
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'subscriptions')) {
            Notification::make()
                ->title(__('Subscription not found'))
                ->danger()
                ->send();

            return;
        }

        $subscription = $billable->subscriptions()->find($subscriptionId);

        if (! $subscription) {
            Notification::make()
                ->title(__('Subscription not found'))
                ->danger()
                ->send();

            return;
        }

        try {
            $subscription->resume();

            Notification::make()
                ->title(__('Subscription resumed'))
                ->body(__('Your subscription has been reactivated.'))
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title(__('Failed to resume subscription'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function formatAmount(int $amount): string
    {
        if (class_exists('\AIArmada\CashierChip\CashierChip')) {
            return \AIArmada\CashierChip\CashierChip::formatAmount($amount);
        }

        $currency = config('cashier-chip.currency', 'MYR');

        return $currency.' '.number_format($amount / 100, 2);
    }

    /**
     * @return Collection<int, mixed>
     */
    protected function getSubscriptions(): Collection
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'subscriptions')) {
            return collect();
        }

        $activeStatuses = $this->getActiveStatuses();

        return $billable->subscriptions()
            ->whereIn('chip_status', $activeStatuses)
            ->get();
    }

    /**
     * @return Collection<int, mixed>
     */
    protected function getCancelledSubscriptions(): Collection
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'subscriptions')) {
            return collect();
        }

        return $billable->subscriptions()
            ->onGracePeriod()
            ->get();
    }

    /**
     * Get active subscription statuses.
     *
     * @return array<int, string>
     */
    protected function getActiveStatuses(): array
    {
        if (class_exists('\AIArmada\CashierChip\Subscription')) {
            return [
                \AIArmada\CashierChip\Subscription::STATUS_ACTIVE,
                \AIArmada\CashierChip\Subscription::STATUS_TRIALING,
                \AIArmada\CashierChip\Subscription::STATUS_PAST_DUE,
            ];
        }

        return ['active', 'trialing', 'past_due'];
    }
}
