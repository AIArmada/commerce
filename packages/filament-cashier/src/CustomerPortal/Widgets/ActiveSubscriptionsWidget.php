<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Widgets;

use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\Cashier\Support\UnifiedSubscription;
use AIArmada\CashierChip\Cashier as CashierChip;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Subscription;

final class ActiveSubscriptionsWidget extends Widget
{
    /** @var view-string */
    protected string $view = 'filament-cashier::customer-portal.widgets.active-subscriptions';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public int $perGatewayLimit = 5;

    /**
     * @return Collection<int, UnifiedSubscription>
     */
    public function getSubscriptions(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        $userIdentifier = $this->resolveAuthIdentifier($user);

        if ($userIdentifier === null) {
            return collect();
        }

        $subscriptions = collect();
        $detector = app(GatewayDetector::class);
        $limit = max(1, $this->perGatewayLimit);

        if (
            $detector->isAvailable('stripe')
            && class_exists(Subscription::class)
            && Schema::hasTable((new Subscription)->getTable())
        ) {
            $stripeSubscriptions = OwnerScopedQuery::apply(Subscription::query())
                ->with('items')
                ->where('user_id', $userIdentifier)
                ->where(function ($query): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn ($sub) => UnifiedSubscription::fromStripe($sub));

            $subscriptions = $subscriptions->merge($stripeSubscriptions);
        }

        if ($detector->isAvailable('chip')) {
            $subscriptionModel = CashierChip::$subscriptionModel;
            $chipSubscriptions = OwnerScopedQuery::apply($subscriptionModel::query())
                ->with(['billable', 'items'])
                ->where('billable_type', $user->getMorphClass())
                ->where('billable_id', (string) $user->getKey())
                ->where(function ($query): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn ($sub) => UnifiedSubscription::fromChip($sub));

            $subscriptions = $subscriptions->merge($chipSubscriptions);
        }

        return $subscriptions->filter(fn (UnifiedSubscription $sub) => $sub->status->isActive());
    }

    private function resolveAuthIdentifier(mixed $user): int | string | null
    {
        if ($user instanceof Model) {
            $identifierName = $user->getKeyName();
            $attributes = $user->getAttributes();
            $attributeIdentifier = $attributes[$identifierName] ?? null;

            if (is_int($attributeIdentifier) || is_string($attributeIdentifier)) {
                return $attributeIdentifier;
            }

            $rawIdentifier = $user->getRawOriginal($identifierName);

            if (is_int($rawIdentifier) || is_string($rawIdentifier)) {
                return $rawIdentifier;
            }

            return null;
        }

        if (is_object($user) && method_exists($user, 'getAuthIdentifier')) {
            $identifier = $user->getAuthIdentifier();

            if (is_int($identifier) || is_string($identifier)) {
                return $identifier;
            }
        }

        return null;
    }
}
