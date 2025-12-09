<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Widgets;

use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;

final class ActiveSubscriptionsWidget extends Widget
{
    protected string $view = 'filament-cashier::customer-portal.widgets.active-subscriptions';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    /**
     * @return Collection<int, UnifiedSubscription>
     */
    public function getSubscriptions(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        $subscriptions = collect();
        $detector = app(GatewayDetector::class);

        if ($detector->isAvailable('stripe') && class_exists(Subscription::class)) {
            $stripeSubscriptions = Subscription::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where(function ($query): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->get()
                ->map(fn ($sub) => UnifiedSubscription::fromStripe($sub));

            $subscriptions = $subscriptions->merge($stripeSubscriptions);
        }

        if ($detector->isAvailable('chip') && class_exists(\AIArmada\CashierChip\Models\Subscription::class)) {
            $chipSubscriptions = \AIArmada\CashierChip\Models\Subscription::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where(function ($query): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->get()
                ->map(fn ($sub) => UnifiedSubscription::fromChip($sub));

            $subscriptions = $subscriptions->merge($chipSubscriptions);
        }

        return $subscriptions->filter(fn (UnifiedSubscription $sub) => $sub->status->isActive());
    }
}
