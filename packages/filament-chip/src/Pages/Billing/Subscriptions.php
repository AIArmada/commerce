<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages\Billing;

use AIArmada\CashierChip\CashierChip;
use AIArmada\CashierChip\Subscription;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class Subscriptions extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament-chip::pages.billing.subscriptions';

    public static function getNavigationLabel(): string
    {
        return __('Subscriptions');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Manage Subscriptions');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-chip.billing.features.subscriptions', true);
    }

    public function getViewData(): array
    {
        $billable = $this->getBillable();

        return [
            'billable' => $billable,
            'subscriptions' => $this->getSubscriptions(),
            'cancelledSubscriptions' => $this->getCancelledSubscriptions(),
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
     * @return Collection<int, Subscription>
     */
    protected function getSubscriptions(): Collection
    {
        $billable = $this->getBillable();

        if (! $billable) {
            return collect();
        }

        return $billable->subscriptions()
            ->whereIn('chip_status', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_PAST_DUE,
            ])
            ->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    protected function getCancelledSubscriptions(): Collection
    {
        $billable = $this->getBillable();

        if (! $billable) {
            return collect();
        }

        return $billable->subscriptions()
            ->onGracePeriod()
            ->get();
    }

    public function cancelSubscription(int $subscriptionId): void
    {
        $subscription = $this->getBillable()?->subscriptions()->find($subscriptionId);

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
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Failed to cancel subscription'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resumeSubscription(int $subscriptionId): void
    {
        $subscription = $this->getBillable()?->subscriptions()->find($subscriptionId);

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
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Failed to resume subscription'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function formatAmount(int $amount): string
    {
        return CashierChip::formatAmount($amount);
    }
}
