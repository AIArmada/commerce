<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Support;

use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\Cashier\Support\UnifiedSubscription;
use AIArmada\CashierChip\Billing\Cashier as CashierChip;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Subscription;

final class CustomerSubscriptionsQuery
{
    private GatewayDetector $detector;

    public function __construct(?GatewayDetector $detector = null)
    {
        $this->detector = $detector ?? app(GatewayDetector::class);
    }

    /**
     * @param  Model  $user  The authenticated billable user.
     * @param  int  $perGatewayLimit  Number of records to fetch per gateway.
     * @param  bool  $fetchExtra  If true, fetches limit+1 per gateway to detect "has more".
     * @return array{items: Collection<int, UnifiedSubscription>, hasMore: bool}
     */
    public function getForUser(Model $user, int $perGatewayLimit = 50, bool $fetchExtra = false): array
    {
        $fetchLimit = $fetchExtra ? $perGatewayLimit + 1 : $perGatewayLimit;
        $userIdentifier = $this->resolveAuthIdentifier($user);

        if ($userIdentifier === null) {
            return ['items' => collect(), 'hasMore' => false];
        }

        $subscriptions = collect();
        $hasMore = false;

        $stripe = $this->getStripeSubscriptions($user, $userIdentifier, $fetchLimit);
        $subscriptions = $subscriptions->merge($stripe['items']);
        if ($stripe['hasMore']) {
            $hasMore = true;
        }

        $chip = $this->getChipSubscriptions($user, $fetchLimit);
        $subscriptions = $subscriptions->merge($chip['items']);
        if ($chip['hasMore']) {
            $hasMore = true;
        }

        return [
            'items' => $subscriptions->sortByDesc('createdAt')->values(),
            'hasMore' => $hasMore,
        ];
    }

    /**
     * @return array{items: Collection<int, UnifiedSubscription>, hasMore: bool}
     */
    public function getStripeSubscriptions(Model $user, int | string $userIdentifier, int $fetchLimit = 51): array
    {
        if (
            ! $this->detector->isAvailable('stripe')
            || ! class_exists(Subscription::class)
            || ! Schema::hasTable((new Subscription)->getTable())
        ) {
            return ['items' => collect(), 'hasMore' => false];
        }

        $models = OwnerScopedQuery::apply(Subscription::query())
            ->with('items')
            ->where('user_id', $userIdentifier)
            ->orderByDesc('created_at')
            ->limit($fetchLimit)
            ->get()
            ->values();

        $hasMore = $models->count() >= $fetchLimit;

        $items = $models
            ->take($fetchLimit - 1)
            ->map(fn ($sub) => UnifiedSubscription::fromStripe($sub));

        return ['items' => $items, 'hasMore' => $hasMore];
    }

    /**
     * @return array{items: Collection<int, UnifiedSubscription>, hasMore: bool}
     */
    public function getChipSubscriptions(Model $user, int $fetchLimit = 51): array
    {
        if (! $this->detector->isAvailable('chip')) {
            return ['items' => collect(), 'hasMore' => false];
        }

        $subscriptionModel = CashierChip::$subscriptionModel;

        $models = OwnerScopedQuery::apply($subscriptionModel::query())
            ->with(['billable', 'items'])
            ->where('billable_type', $user->getMorphClass())
            ->where('billable_id', (string) $user->getKey())
            ->orderByDesc('created_at')
            ->limit($fetchLimit)
            ->get()
            ->values();

        $hasMore = $models->count() >= $fetchLimit;

        $items = $models
            ->take($fetchLimit - 1)
            ->map(fn ($sub) => UnifiedSubscription::fromChip($sub));

        return ['items' => $items, 'hasMore' => $hasMore];
    }

    private function resolveAuthIdentifier(Model $user): int | string | null
    {
        $identifierName = $user->getKeyName();
        $attributes = $user->getAttributes();
        $attributeIdentifier = $attributes[$identifierName] ?? null;

        if (is_int($attributeIdentifier) || is_string($attributeIdentifier)) {
            return $attributeIdentifier;
        }

        return $user->getKey();
    }
}
