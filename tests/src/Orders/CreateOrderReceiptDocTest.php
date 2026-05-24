<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Orders\Actions\CreateOrderReceiptDoc;
use AIArmada\Orders\Models\Order;

it('creates a receipt document for a paid order', function (): void {
    $order = receiptDocumentOrder('receipt-test');

    $receipt = app(CreateOrderReceiptDoc::class)->execute($order, 'txn_receipt_123', 'chip');

    expect($receipt->doc_type)->toBe(DocType::Receipt->value)
        ->and($receipt->docable_type)->toBe($order->getMorphClass())
        ->and($receipt->docable_id)->toBe((string) $order->getKey())
        ->and(data_get($receipt->metadata, 'payment_gateway'))->toBe('chip')
        ->and(data_get($receipt->metadata, 'payment_transaction_id'))->toBe('txn_receipt_123')
        ->and(data_get($receipt->metadata, 'generated_by'))->toBe(CreateOrderReceiptDoc::class);
});

it('returns the existing receipt document when one already exists', function (): void {
    $order = receiptDocumentOrder('receipt-existing');

    $firstReceipt = app(CreateOrderReceiptDoc::class)->execute($order, 'txn_receipt_abc', 'chip');
    $secondReceipt = app(CreateOrderReceiptDoc::class)->execute($order->fresh(['items', 'billingAddress']) ?? $order, 'txn_receipt_abc', 'chip');

    expect((string) $secondReceipt->getKey())->toBe((string) $firstReceipt->getKey())
        ->and(Doc::query()
            ->where('docable_type', $order->getMorphClass())
            ->where('docable_id', $order->getKey())
            ->where('doc_type', DocType::Receipt->value)
            ->count())->toBe(1);
});

function receiptDocumentOrder(string $suffix): Order
{
    $order = Order::query()->create([
        'order_number' => 'ORD-RECEIPT-' . $suffix,
        'subtotal' => 9700,
        'discount_total' => 0,
        'shipping_total' => 0,
        'tax_total' => 0,
        'grand_total' => 9700,
        'currency' => 'MYR',
        'paid_at' => now(),
    ]);

    $order->items()->create([
        'name' => 'Receipt Product',
        'sku' => 'receipt-product-' . $suffix,
        'quantity' => 1,
        'unit_price' => 9700,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'currency' => 'MYR',
    ]);

    $order->addresses()->create([
        'type' => 'billing',
        'first_name' => 'Receipt',
        'last_name' => 'Customer',
        'line1' => '123 Receipt Street',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country' => 'MY',
        'email' => 'receipt+' . $suffix . '@example.com',
        'phone' => '0123456789',
    ]);

    return $order->fresh(['items', 'billingAddress']) ?? $order;
}
