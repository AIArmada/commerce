<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Actions\Concerns\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\OrderItemFulfillmentMarker;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use InvalidArgumentException;

final readonly class ManageItemFulfillmentMarker implements OrderItemFulfillmentMarker
{
    use AssertsOrderOwnerBoundary;

    public function mark(string $orderItemId, string $key, string $value): ?array
    {
        $this->validateMarker($key, $value);

        return (new OrderItem)->getConnection()->transaction(function () use ($orderItemId, $key, $value): ?array {
            $item = $this->lockItem($orderItemId);

            if ($item === null) {
                return null;
            }

            $original = $item->options;
            $options = $original ?? [];

            if (($options[$key] ?? null) === $value) {
                return null;
            }

            $item->options = array_replace($options, [$key => $value]);
            $item->save();

            return ['options' => $original];
        });
    }

    public function restore(string $orderItemId, string $key, string $value, ?array $originalOptions): void
    {
        $this->validateMarker($key, $value);

        (new OrderItem)->getConnection()->transaction(function () use ($orderItemId, $key, $value, $originalOptions): void {
            $item = $this->lockItem($orderItemId);

            if ($item === null || ($item->options[$key] ?? null) !== $value) {
                return;
            }

            $options = $item->options ?? [];

            if ($originalOptions !== null && array_key_exists($key, $originalOptions)) {
                $options[$key] = $originalOptions[$key];
            } else {
                unset($options[$key]);
            }

            $item->options = $options === [] && $originalOptions === null ? null : $options;
            $item->save();
        });
    }

    private function lockItem(string $orderItemId): ?OrderItem
    {
        $item = OrderItem::query()->forOwner()->find($orderItemId);

        if ($item === null) {
            return null;
        }

        $order = Order::query()->forOwner()->lockForUpdate()->findOrFail($item->order_id);
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        return $order->items()->lockForUpdate()->find($orderItemId);
    }

    private function validateMarker(string $key, string $value): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*_fulfillment$/D', $key) !== 1 || mb_trim($value) === '') {
            throw new InvalidArgumentException('A namespaced fulfillment marker and nonempty value are required.');
        }
    }
}
