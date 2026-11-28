<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Queries;

use AIArmada\Cashier\Support\BillingCurrency;
use AIArmada\Cashier\Support\UnifiedSubscription;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

final readonly class MonthlySubscriptionRevenueQuery
{
    public function __construct(private BillingAnalyticsQuery $analytics) {}

    /**
     * @param  array<string, array{end: CarbonImmutable, revenue: int}>  $months
     * @return array<string, int>
     */
    public function getRevenueByMonth(string $gateway, DateTimeInterface $windowStart, array $months, string $base, bool $convert, array $rates): array
    {
        $query = $this->analytics->subscriptions($gateway);

        if ($query === null) {
            return array_map(static fn (array $month): int => $month['revenue'], $months);
        }

        $query->with('items')
            ->whereBetween('created_at', [$windowStart, CarbonImmutable::now()])
            ->chunkById(200, function (Collection $subscriptions) use ($gateway, &$months, $base, $convert, $rates): void {
                foreach ($subscriptions as $subscription) {
                    $unified = $gateway === 'stripe'
                        ? UnifiedSubscription::fromStripe($subscription)
                        : UnifiedSubscription::fromChip($subscription);

                    if (! $unified->status->isActive()) {
                        continue;
                    }

                    $bucket = $unified->createdAt->format('Y-m');

                    if (! isset($months[$bucket])) {
                        continue;
                    }

                    if ($unified->endsAt !== null && ! $unified->endsAt->isAfter($months[$bucket]['end'])) {
                        continue;
                    }

                    $months[$bucket]['revenue'] += BillingCurrency::total([$unified->currency => $unified->amount], $base, $convert, $rates);
                }
            });

        return array_map(static fn (array $month): int => $month['revenue'], $months);
    }
}
