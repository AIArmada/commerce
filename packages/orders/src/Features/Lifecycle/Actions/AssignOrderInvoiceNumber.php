<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Contracts\OrderInvoiceNumber;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Support\AssertsOrderOwnerBoundary;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

final readonly class AssignOrderInvoiceNumber implements OrderInvoiceNumber
{
    use AssertsOrderOwnerBoundary;

    public function assign(string $orderId): string
    {
        $order = Order::query()->forOwner()->findOrFail($orderId);
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        $number = $order->getConnection()->transaction(function () use ($order): string {
            $lockedOrder = $order->newQuery()->forOwner()->lockForUpdate()->findOrFail($order->getKey());
            $this->assertOwnerBoundaryForMutation($lockedOrder, __METHOD__);

            if (is_string($lockedOrder->invoice_number) && mb_trim($lockedOrder->invoice_number) !== '') {
                return $lockedOrder->invoice_number;
            }

            for ($attempt = 1; ; $attempt++) {
                $number = $this->generateNumber();

                try {
                    $lockedOrder->getConnection()->transaction(function () use ($lockedOrder, $number): void {
                        $lockedOrder->invoice_number = $number;
                        $lockedOrder->save();
                    });

                    return $number;
                } catch (UniqueConstraintViolationException $exception) {
                    if ($attempt >= 3) {
                        throw $exception;
                    }
                }
            }
        });

        $order->invoice_number = $number;
        $order->syncOriginalAttribute('invoice_number');

        return $number;
    }

    private function generateNumber(): string
    {
        $prefix = config('orders.invoice.prefix', 'INV');
        $separator = config('orders.invoice.separator', '-');
        $dateFormat = config('orders.invoice.date_format', 'Ymd');
        $randomLength = (int) config('orders.invoice.random_length', 6);

        return $prefix . $separator . CarbonImmutable::now()->format($dateFormat) . $separator . mb_strtoupper(Str::random($randomLength));
    }
}
