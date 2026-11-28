<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Payments\Actions;

use AIArmada\Orders\Actions\Concerns\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\OrderPaymentRetention;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final readonly class RemoveOrderPaymentRecords implements OrderPaymentRetention
{
    use AssertsOrderOwnerBoundary;

    public function removeForOrder(string $orderId, bool $force = false): void
    {
        (new Order)->getConnection()->transaction(function () use ($orderId, $force): void {
            $order = Order::query()->forOwner()->lockForUpdate()->findOrFail($orderId);
            $this->assertOwnerBoundaryForMutation($order, __METHOD__);

            if (! $force && ($order->isPaid() || $order->getTotalPaid() > 0 || $order->isFinal())) {
                throw new LogicException('Paid or final orders require an explicit administrative retention override.');
            }

            foreach ([$order->refunds(), $order->payments()] as $relation) {
                $mismatched = $relation->withoutOwnerScope()
                    ->where(function (Builder $query) use ($order): void {
                        foreach (['owner_type' => $order->owner_type, 'owner_id' => $order->owner_id] as $column => $value) {
                            if ($value === null) {
                                $query->orWhereNotNull($column);
                            } else {
                                $query->orWhereNull($column)->orWhere($column, '<>', $value);
                            }
                        }
                    })->exists();

                if ($mismatched) {
                    throw new LogicException('Order payment or refund ownership is inconsistent.');
                }
            }

            $order->refunds()->withoutOwnerScope()->delete();
            $order->payments()->withoutOwnerScope()->delete();
        });
    }
}
