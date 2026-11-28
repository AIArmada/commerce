<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\Concerns\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\OrderAdministration;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\PendingPayment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final readonly class ManageOrders implements OrderAdministration
{
    use AssertsOrderOwnerBoundary;

    public function __construct(private CreateOrder $orders) {}

    public function createDraft(?string $notes = null, ?string $internalNotes = null): string
    {
        if (config('orders.owner.enabled', false)) {
            OwnerContext::assertResolvedOrExplicitGlobal(OwnerContext::resolve(), 'An owner context is required to create an order.');
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): string => (string) Order::query()->create([
                    'status' => Created::class,
                    'currency' => config('orders.currency.default', 'MYR'),
                    'notes' => $notes,
                    'internal_notes' => $internalNotes,
                ])->getKey());
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3 || ! str_contains(mb_strtolower($exception->getMessage()), 'order_number')) {
                    throw $exception;
                }
            }
        }
    }

    public function updateNotes(string $orderId, array $data): void
    {
        $attributes = array_intersect_key($data, array_flip(['notes', 'internal_notes']));

        foreach ($attributes as $value) {
            if ($value !== null && ! is_string($value)) {
                throw new InvalidArgumentException('Order notes must be strings or null.');
            }
        }

        DB::transaction(function () use ($orderId, $attributes): void {
            $order = $this->lockOrder($orderId);

            if ($order->isFinal()) {
                throw new LogicException('Final orders cannot be edited.');
            }

            $order->update($attributes);
        });
    }

    public function addItem(string $orderId, array $itemData): string
    {
        return (string) $this->orders->addItem(Order::query()->findOrFail($orderId), $itemData)->getKey();
    }

    public function addAddress(string $orderId, array $addressData, string $type): void
    {
        $this->orders->addAddress(Order::query()->findOrFail($orderId), $addressData, $type);
    }

    public function recalculateTotals(string $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            $order = $this->lockOrder($orderId);

            if (! $order->canBeModified() || $order->payments()->exists()) {
                throw new LogicException('Only unpaid editable orders can recalculate totals.');
            }

            $order->recalculateTotals()->save();
        });
    }

    public function deleteDrafts(array $orderIds): void
    {
        $orderIds = array_values(array_unique($orderIds));
        sort($orderIds, SORT_STRING);

        DB::transaction(function () use ($orderIds): void {
            foreach ($orderIds as $orderId) {
                $order = $this->lockOrder($orderId);
                if ((! $order->status->equals(Created::class) && ! $order->status->equals(PendingPayment::class))
                    || $order->payments()->exists() || $order->refunds()->exists()) {
                    throw new LogicException('Only draft or awaiting-payment orders without payment or refund records can be deleted.');
                }

                if ($order->delete() !== true) {
                    throw new LogicException('Order deletion was rejected.');
                }
            }
        });
    }

    private function lockOrder(string $orderId): Order
    {
        $order = Order::query()->lockForUpdate()->findOrFail($orderId);
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        return $order;
    }
}
