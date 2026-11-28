<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Queries;

use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\Cashier\Support\UnifiedSubscription;
use AIArmada\CashierChip\Billing\Cashier as CashierChip;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;

final class BillingAnalyticsQuery
{
    public function __construct(private GatewayDetector $detector) {}

    /** @return array{count: int, mrrByCurrency: array<string, int>, countByGateway: array<string, int>, mrrByGateway: array<string, array<string, int>>} */
    public function activeSummary(): array
    {
        $summary = ['count' => 0, 'mrrByCurrency' => [], 'countByGateway' => [], 'mrrByGateway' => []];
        $now = CarbonImmutable::now();

        foreach ($this->detector->availableGateways() as $gateway) {
            $query = $this->subscriptions($gateway);
            $summary['countByGateway'][$gateway] = 0;
            $summary['mrrByGateway'][$gateway] = [];

            if ($query === null) {
                continue;
            }

            $query->with('items')
                ->where(fn (Builder $query): Builder => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
                ->chunkById(200, function (Collection $models) use ($gateway, &$summary): void {
                    foreach ($models as $model) {
                        $subscription = $gateway === 'stripe'
                            ? UnifiedSubscription::fromStripe($model)
                            : UnifiedSubscription::fromChip($model);

                        if (! $subscription->status->isActive()) {
                            continue;
                        }

                        $currency = $subscription->currency;
                        $amount = $this->monthlyAmount($subscription);
                        $summary['count']++;
                        $summary['countByGateway'][$gateway]++;
                        $summary['mrrByCurrency'][$currency] = ($summary['mrrByCurrency'][$currency] ?? 0) + $amount;
                        $summary['mrrByGateway'][$gateway][$currency] = ($summary['mrrByGateway'][$gateway][$currency] ?? 0) + $amount;
                    }
                });
        }

        return $summary;
    }

    /** @return array{current: int, previous: int} */
    public function cancellations(): array
    {
        $now = CarbonImmutable::now();
        $start = $now->startOfMonth();
        $previous = $start->subMonthNoOverflow();
        $counts = ['current' => 0, 'previous' => 0];

        foreach ($this->detector->availableGateways() as $gateway) {
            $query = $this->subscriptions($gateway);

            if ($query === null) {
                continue;
            }

            $counts['current'] += (clone $query)->whereBetween('ends_at', [$start, $now])->count();
            $counts['previous'] += (clone $query)->where('ends_at', '>=', $previous)->where('ends_at', '<', $start)->count();
        }

        return $counts;
    }

    public function subscriptions(string $gateway): ?Builder
    {
        if (! $this->detector->isAvailable($gateway)) {
            return null;
        }

        if ($gateway === 'stripe' && class_exists(Subscription::class)) {
            return OwnerScopedQuery::apply(Subscription::query());
        }

        if ($gateway === 'chip' && class_exists(CashierChip::class)) {
            $model = CashierChip::$subscriptionModel;

            return OwnerScopedQuery::apply($model::query());
        }

        return null;
    }

    private function monthlyAmount(UnifiedSubscription $subscription): int
    {
        $attributes = $subscription->original->getAttributes();
        $count = max(1, (int) ($attributes['billing_interval_count'] ?? 1));

        return (int) round(match ($attributes['billing_interval'] ?? 'month') {
            'year' => $subscription->amount / (12 * $count),
            'week' => $subscription->amount * 52 / (12 * $count),
            'day' => $subscription->amount * 365 / (12 * $count),
            default => $subscription->amount / $count,
        });
    }
}
