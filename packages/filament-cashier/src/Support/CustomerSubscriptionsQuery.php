<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Support;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\UnifiedSubscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

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
        if (! $user instanceof BillableContract) {
            return ['items' => collect(), 'hasMore' => false];
        }

        $subscriptions = collect();
        $hasMore = false;

        $stripe = $this->getStripeSubscriptions($user, $user->getKey(), $fetchLimit, $fetchExtra);
        $subscriptions = $subscriptions->merge($stripe['items']);
        if ($stripe['hasMore']) {
            $hasMore = true;
        }

        $chip = $this->getChipSubscriptions($user, $fetchLimit, $fetchExtra);
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
    public function getStripeSubscriptions(Model $user, int | string $userIdentifier, int $fetchLimit = 51, bool $fetchExtra = false): array
    {
        if (! $user instanceof BillableContract || ! $this->detector->isAvailable('stripe')) {
            return ['items' => collect(), 'hasMore' => false];
        }

        return $this->getGatewaySubscriptions($user, 'stripe', $fetchLimit, $fetchExtra);
    }

    /**
     * @return array{items: Collection<int, UnifiedSubscription>, hasMore: bool}
     */
    public function getChipSubscriptions(Model $user, int $fetchLimit = 51, bool $fetchExtra = false): array
    {
        if (! $user instanceof BillableContract || ! $this->detector->isAvailable('chip')) {
            return ['items' => collect(), 'hasMore' => false];
        }

        return $this->getGatewaySubscriptions($user, 'chip', $fetchLimit, $fetchExtra);
    }

    /**
     * @return array{items: Collection<int, UnifiedSubscription>, hasMore: bool}
     */
    private function getGatewaySubscriptions(BillableContract $user, string $gateway, int $fetchLimit, bool $fetchExtra): array
    {
        try {
            $records = Cashier::gateway($gateway)->subscriptions($user)->take($fetchLimit);
        } catch (Throwable) {
            return ['items' => collect(), 'hasMore' => false];
        }

        $hasMore = $fetchExtra && $records->count() >= $fetchLimit;
        $items = $records
            ->take($fetchExtra ? max(0, $fetchLimit - 1) : $fetchLimit)
            ->filter(fn (mixed $subscription): bool => $subscription instanceof SubscriptionContract)
            ->map(fn (SubscriptionContract $subscription): UnifiedSubscription => UnifiedSubscription::fromGateway($subscription));

        return [
            'items' => $items,
            'hasMore' => $hasMore,
        ];
    }
}
