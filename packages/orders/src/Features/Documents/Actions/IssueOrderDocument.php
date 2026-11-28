<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Documents\Actions;

use AIArmada\Orders\Contracts\OrderDocuments;
use AIArmada\Orders\Models\Order;
use InvalidArgumentException;

final readonly class IssueOrderDocument implements OrderDocuments
{
    public function issue(string $orderId, string $type, string $transactionId, string $gateway): ?string
    {
        $order = Order::query()->findOrFail($orderId);

        return match ($type) {
            'invoice' => app(CreateOrderInvoiceDoc::class)->execute($order, $transactionId, $gateway),
            'receipt' => app(CreateOrderReceiptDoc::class)->execute($order, $transactionId, $gateway),
            default => throw new InvalidArgumentException("Unsupported order document type [{$type}]."),
        };
    }
}
