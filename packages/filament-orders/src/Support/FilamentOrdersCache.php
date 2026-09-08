<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Support;

use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\Fraud;
use AIArmada\Orders\States\OnHold;
use AIArmada\Orders\States\PaymentFailed;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use AIArmada\Orders\States\Returned;
use AIArmada\Orders\States\Shipped;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class FilamentOrdersCache
{
    /**
     * @return array{
     *     todayOrders: int,
     *     yesterdayOrders: int,
     *     todayRevenue: int,
     *     yesterdayRevenue: int,
     *     pendingOrders: int,
     *     monthlyRevenue: int,
     *     lastMonthRevenue: int,
     *     statusCounts: array<string, int>
     * }
     */
    public static function rememberStats(bool $includeGlobal = false): array
    {
        $owner = OwnerContext::resolve();
        $logicalKey = $includeGlobal
            ? 'filament-orders.stats.with-global'
            : 'filament-orders.stats.owner-only';

        /** @var array{
         *     todayOrders: int,
         *     yesterdayOrders: int,
         *     todayRevenue: int,
         *     yesterdayRevenue: int,
         *     pendingOrders: int,
         *     monthlyRevenue: int,
         *     lastMonthRevenue: int,
         *     statusCounts: array<string, int>
         * } $stats */
        $stats = OwnerCache::remember(
            $owner,
            $logicalKey,
            CarbonImmutable::now()->addSeconds(15),
            fn (): array => self::queryStats($includeGlobal),
        );

        return $stats;
    }

    public static function forgetForOrder(Order $order): void
    {
        $owner = self::ownerFromOrder($order);

        OwnerCache::forget($owner, 'filament-orders.stats.owner-only');
        OwnerCache::forget($owner, 'filament-orders.stats.with-global');

        // A global order is included in every tenant's with-global view. Those
        // keys cannot be enumerated safely; the short TTL bounds staleness.
    }

    /**
     * @return array{
     *     todayOrders: int,
     *     yesterdayOrders: int,
     *     todayRevenue: int,
     *     yesterdayRevenue: int,
     *     pendingOrders: int,
     *     monthlyRevenue: int,
     *     lastMonthRevenue: int,
     *     statusCounts: array<string, int>
     * }
     */
    private static function queryStats(bool $includeGlobal): array
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();
        $tomorrow = $today->addDay();
        $yesterday = $today->subDay();
        $thisMonth = $now->startOfMonth();
        $nextMonth = $thisMonth->addMonth();
        $lastMonth = $thisMonth->subMonth();

        $statusNames = [
            'created' => Created::$name,
            'pending_payment' => PendingPayment::$name,
            'processing' => Processing::$name,
            'on_hold' => OnHold::$name,
            'shipped' => Shipped::$name,
            'delivered' => Delivered::$name,
            'completed' => Completed::$name,
            'canceled' => Canceled::$name,
            'returned' => Returned::$name,
            'refunded' => Refunded::$name,
            'fraud' => Fraud::$name,
            'payment_failed' => PaymentFailed::$name,
        ];

        $selects = [
            'COUNT(*) AS total_orders',
            'SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS today_orders',
            'SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS yesterday_orders',
            'SUM(CASE WHEN created_at >= ? AND created_at < ? AND paid_at IS NOT NULL THEN grand_total ELSE 0 END) AS today_revenue',
            'SUM(CASE WHEN created_at >= ? AND created_at < ? AND paid_at IS NOT NULL THEN grand_total ELSE 0 END) AS yesterday_revenue',
            'SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS pending_orders',
            'SUM(CASE WHEN created_at >= ? AND created_at < ? AND paid_at IS NOT NULL THEN grand_total ELSE 0 END) AS monthly_revenue',
            'SUM(CASE WHEN created_at >= ? AND created_at < ? AND paid_at IS NOT NULL THEN grand_total ELSE 0 END) AS last_month_revenue',
        ];

        $bindings = [
            $today, $tomorrow,
            $yesterday, $today,
            $today, $tomorrow,
            $yesterday, $today,
            PendingPayment::$name, Processing::$name,
            $thisMonth, $nextMonth,
            $lastMonth, $thisMonth,
        ];

        foreach ($statusNames as $key => $statusName) {
            $selects[] = sprintf(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS status_%s',
                $key,
            );
            $bindings[] = $statusName;
        }

        /** @var Builder<Order> $query */
        $query = Order::query()->forOwner(includeGlobal: $includeGlobal);
        $row = $query->selectRaw(implode(', ', $selects), $bindings)->first();

        $countsByStatus = [];

        foreach (array_keys($statusNames) as $key) {
            $countsByStatus[$key] = (int) ($row?->getAttribute('status_' . $key) ?? 0);
        }

        return [
            'todayOrders' => (int) ($row?->getAttribute('today_orders') ?? 0),
            'yesterdayOrders' => (int) ($row?->getAttribute('yesterday_orders') ?? 0),
            'todayRevenue' => (int) ($row?->getAttribute('today_revenue') ?? 0),
            'yesterdayRevenue' => (int) ($row?->getAttribute('yesterday_revenue') ?? 0),
            'pendingOrders' => (int) ($row?->getAttribute('pending_orders') ?? 0),
            'monthlyRevenue' => (int) ($row?->getAttribute('monthly_revenue') ?? 0),
            'lastMonthRevenue' => (int) ($row?->getAttribute('last_month_revenue') ?? 0),
            'statusCounts' => $countsByStatus,
        ];
    }

    private static function ownerFromOrder(Order $order): ?OwnerScopeIdentifiable
    {
        /** @var string|null $ownerType */
        $ownerType = $order->getAttribute('owner_type');

        /** @var string|int|null $ownerId */
        $ownerId = $order->getAttribute('owner_id');

        if ($ownerType === null && $ownerId === null) {
            return null;
        }

        if ($ownerType === null || $ownerId === null || $ownerType === '' || (is_string($ownerId) && $ownerId === '')) {
            throw new InvalidArgumentException('Order owner type and owner id must be either both null or both non-empty.');
        }

        return new class($ownerType, $ownerId) implements OwnerScopeIdentifiable
        {
            public function __construct(
                private readonly string $type,
                private readonly string | int $id,
            ) {}

            public function getMorphClass(): string
            {
                return $this->type;
            }

            public function getKey(): string | int
            {
                return $this->id;
            }
        };
    }
}
