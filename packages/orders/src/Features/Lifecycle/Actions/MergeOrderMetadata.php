<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Support\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\OrderMetadataWriter;
use AIArmada\Orders\Models\Order;

final readonly class MergeOrderMetadata implements OrderMetadataWriter
{
    use AssertsOrderOwnerBoundary;

    /** @param array<string, mixed> $patch */
    public function merge(string $orderId, array $patch): void
    {
        (new Order)->getConnection()->transaction(function () use ($orderId, $patch): void {
            $order = Order::query()->forOwner()->lockForUpdate()->findOrFail($orderId);
            $this->assertOwnerBoundaryForMutation($order, 'merge order metadata');

            $current = is_array($order->metadata) ? $order->metadata : [];
            $order->update(['metadata' => array_replace($current, $patch)]);
        }, attempts: 3);
    }
}
