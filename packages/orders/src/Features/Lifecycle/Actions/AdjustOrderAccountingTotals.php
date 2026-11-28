<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Support\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\OrderAccountingTotals;
use AIArmada\Orders\Models\Order;
use InvalidArgumentException;

final readonly class AdjustOrderAccountingTotals implements OrderAccountingTotals
{
    use AssertsOrderOwnerBoundary;

    public function adjust(string $orderId, string $column, int $delta): void
    {
        if (! in_array($column, ['paid_total', 'refunded_total', 'pending_refunded_total'], true)) {
            throw new InvalidArgumentException('Invalid order accounting total.');
        }

        if ($delta === 0) {
            return;
        }

        $order = Order::query()->forOwner()->findOrFail($orderId);
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        $query = $order->newQuery()->forOwner()->whereKey($orderId);

        if ($delta > 0) {
            $query->increment($column, $delta);
        } else {
            if ($delta === PHP_INT_MIN) {
                throw new InvalidArgumentException('Order accounting delta exceeds the supported range.');
            }

            $query->decrement($column, -$delta);
        }
    }
}
