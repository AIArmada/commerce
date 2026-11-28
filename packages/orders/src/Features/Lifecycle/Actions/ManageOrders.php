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
                return (new Order)->getConnection()->transaction(fn (): string => (string) Order::query()->create([
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

        (new Order)->getConnection()->transaction(function () use ($orderId, $attributes): void {
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
        (new Order)->getConnection()->transaction(function () use ($orderId): void {
            $order = $this->lockOrder($orderId);

            if (! $order->canBeModified() || $order->payments()->exists()) {
                throw new LogicException('Only unpaid editable orders can recalculate totals.');
            }

            // The order discount already includes line discounts. Aggregate
            // all three values together so the breakdown is checked against
            // the same snapshot used for the total.
            $totals = $order->items()->toBase()
                ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) AS subtotal')
                ->selectRaw('COALESCE(SUM(tax_amount), 0) AS tax_total')
                ->selectRaw('COALESCE(SUM(discount_amount), 0) AS line_discount')
                ->first();

            $subtotal = $this->minorUnits($totals->subtotal ?? 0);
            $tax = $this->minorUnits($totals->tax_total ?? 0);
            $lineDiscount = $this->minorUnits($totals->line_discount ?? 0);
            $shipping = $this->minorUnits($order->shipping_total);
            $discount = $this->minorUnits($order->discount_total);

            if ($lineDiscount > $discount) {
                throw new InvalidArgumentException('Line discounts must be included in the order discount total.');
            }

            if ($subtotal > PHP_INT_MAX - $tax || $subtotal + $tax > PHP_INT_MAX - $shipping) {
                throw new InvalidArgumentException('Order totals exceed the supported integer range.');
            }

            $order->subtotal = $subtotal;
            $order->tax_total = $tax;
            $order->grand_total = max(0, $subtotal + $tax + $shipping - $discount);
            $order->save();
        });
    }

    public function deleteDrafts(array $orderIds): void
    {
        $orderIds = array_values(array_unique($orderIds));
        sort($orderIds, SORT_STRING);

        (new Order)->getConnection()->transaction(function () use ($orderIds): void {
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

    private function minorUnits(mixed $value): int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            $digits = mb_ltrim($value, '0');
            $maximum = (string) PHP_INT_MAX;

            if (mb_strlen($digits) < mb_strlen($maximum) || (mb_strlen($digits) === mb_strlen($maximum) && strcmp($digits, $maximum) <= 0)) {
                return (int) $value;
            }
        }

        throw new InvalidArgumentException('Order totals must be nonnegative integers within the supported range.');
    }

    private function lockOrder(string $orderId): Order
    {
        $order = Order::query()->lockForUpdate()->findOrFail($orderId);
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        return $order;
    }
}
