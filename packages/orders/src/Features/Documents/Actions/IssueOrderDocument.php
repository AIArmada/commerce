<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Documents\Actions;

use AIArmada\Orders\Actions\Concerns\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\OrderDocuments;
use AIArmada\Orders\Models\Order;
use InvalidArgumentException;

final readonly class IssueOrderDocument implements OrderDocuments
{
    use AssertsOrderOwnerBoundary;

    public function issue(string $orderId, string $type, string $transactionId, string $gateway): ?string
    {
        $order = Order::query()->findOrFail($orderId);
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        return match ($type) {
            'invoice' => app(CreateOrderInvoiceDoc::class)->execute($order, $transactionId, $gateway),
            'receipt' => app(CreateOrderReceiptDoc::class)->execute($order, $transactionId, $gateway),
            default => throw new InvalidArgumentException("Unsupported order document type [{$type}]."),
        };
    }
}
