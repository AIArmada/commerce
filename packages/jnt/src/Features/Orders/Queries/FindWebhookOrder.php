<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Queries;

use AIArmada\Jnt\Features\Orders\Api\ReadWebhookOrder;
use AIArmada\Jnt\Features\Orders\Api\WebhookOrder;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class FindWebhookOrder implements ReadWebhookOrder
{
    public function findByTrackingNumber(string $trackingNumber): ?WebhookOrder
    {
        $orders = JntOrder::query()->withoutOwnerScope()
            ->where('tracking_number', $trackingNumber)
            ->limit(2)->get();

        if ($orders->count() > 1) {
            throw new DomainException('Tracking number identifies more than one order.');
        }

        $order = $orders->first();

        return $order === null ? null : $this->snapshot($order);
    }

    public function lockForUpdate(WebhookOrder $expected): WebhookOrder
    {
        if (DB::transactionLevel() === 0) {
            throw new DomainException('Tracking synchronization requires a transaction.');
        }

        $query = JntOrder::query()->withoutOwnerScope()->whereKey($expected->id);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $query->lockForUpdate();
        }

        $order = $query->first();

        if ($order === null || $order->order_id !== $expected->orderReference
            || $order->tracking_number !== $expected->trackingNumber
            || $order->owner_type !== $expected->ownerType
            || ($order->owner_id === null ? null : (string) $order->owner_id) !== $expected->ownerId) {
            throw new DomainException('The tracked order changed during webhook synchronization.');
        }

        return $this->snapshot($order);
    }

    private function snapshot(JntOrder $order): WebhookOrder
    {
        return new WebhookOrder(
            $order->id,
            $order->order_id,
            $order->tracking_number,
            $order->owner_type,
            $order->owner_id === null ? null : (string) $order->owner_id,
            $order->problem_at === null ? null : CarbonImmutable::instance($order->problem_at),
            $order->exception_at === null ? null : CarbonImmutable::instance($order->exception_at),
            $order->returned_at === null ? null : CarbonImmutable::instance($order->returned_at),
            $order->delivered_at === null ? null : CarbonImmutable::instance($order->delivered_at),
        );
    }
}
