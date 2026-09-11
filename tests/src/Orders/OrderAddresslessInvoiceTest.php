<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Orders\Actions\CreateOrder;
use AIArmada\Orders\Actions\CreateOrderInvoiceDoc;
use AIArmada\Orders\Actions\GenerateInvoice;
use AIArmada\Orders\Models\Order;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('keeps a digital order addressless through persistence snapshot and invoice rendering', function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('docs.owner.enabled', false);

    $order = OwnerContext::withOwner(null, fn (): Order => app(CreateOrder::class)->execute(
        orderData: [
            'order_number' => 'ORD-DIGITAL-' . uniqid(),
            'currency' => 'MYR',
            'subtotal' => 5000,
            'grand_total' => 5000,
        ],
        items: [[
            'name' => 'Digital Product',
            'quantity' => 1,
            'unit_price' => 5000,
            'currency' => 'MYR',
            'metadata' => ['requires_shipping' => false],
        ]],
    ));

    $snapshot = $order->fresh(['items', 'addresses']) ?? $order;

    expect($snapshot)->toBeInstanceOf(Order::class)
        ->and($snapshot?->relationLoaded('addresses'))->toBeTrue()
        ->and($snapshot?->addresses)->toBeEmpty()
        ->and($snapshot?->primaryAddress('billing'))->toBeNull()
        ->and($snapshot?->primaryAddress('shipping'))->toBeNull()
        ->and(Address::query()->count())->toBe(0);

    $invoice = OwnerContext::withOwner(null, fn () => app(CreateOrderInvoiceDoc::class)->execute(
        $snapshot,
        'txn-digital-order',
        'chip',
    ));

    expect($invoice)->not->toBeNull()
        ->and($invoice?->doc_type)->toBe(DocType::Invoice->value);

    view()->addNamespace('orders', dirname(__DIR__, 3) . '/packages/orders/resources/views');

    $response = (new GenerateInvoice)->download($snapshot);
    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $html = (string) ob_get_clean();

    expect($html)->toContain('<h2>Invoice</h2>')
        ->and($html)->not->toContain('Bill To')
        ->and($html)->not->toContain('Ship To')
        ->and($snapshot->addresses()->count())->toBe(0);
});

it('renders canonical customer and address data, then omits the block after detaching addresses', function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('docs.owner.enabled', false);

    $order = OwnerContext::withOwner(null, function (): Order {
        $order = Order::factory()->create([
            'order_number' => 'ORD-ADDRESSFUL-' . uniqid(),
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);
        $order->items()->create([
            'name' => 'Physical Product',
            'quantity' => 1,
            'unit_price' => 10000,
            'currency' => 'MYR',
        ]);

        $billing = Address::create([
            'line1' => '123 Billing Street',
            'city' => 'Kuala Lumpur',
            'state' => 'KL',
            'postcode' => '50000',
            'country_code' => 'MY',
            'metadata' => [
                Order::ADDRESS_CONTACT_METADATA_KEY => [
                    'first_name' => 'Billing',
                    'last_name' => 'Customer',
                    'company' => 'Billing Company',
                    'email' => 'billing@example.com',
                    'phone' => '0123456789',
                ],
            ],
        ]);
        $shipping = Address::create([
            'line1' => '456 Shipping Avenue',
            'city' => 'Petaling Jaya',
            'state' => 'Selangor',
            'postcode' => '47810',
            'country_code' => 'MY',
            'metadata' => [
                Order::ADDRESS_CONTACT_METADATA_KEY => [
                    'first_name' => 'Shipping',
                    'last_name' => 'Customer',
                    'company' => 'Shipping Company',
                    'phone' => '0198765432',
                ],
            ],
        ]);

        $order->attachAddress($billing, type: 'billing', isPrimary: true);
        $order->attachAddress($shipping, type: 'shipping', isPrimary: true);

        return $order->fresh(['items', 'addresses']) ?? $order;
    });

    $invoice = OwnerContext::withOwner(null, fn () => app(CreateOrderInvoiceDoc::class)->execute(
        $order,
        'txn-addressful-order',
        'chip',
    ));

    expect($invoice)->not->toBeNull()
        ->and($invoice?->customer_data)->toMatchArray([
            'name' => 'Billing Customer',
            'email' => 'billing@example.com',
            'company' => 'Billing Company',
        ]);

    view()->addNamespace('orders', dirname(__DIR__, 3) . '/packages/orders/resources/views');

    $addressfulResponse = (new GenerateInvoice)->download($order);
    expect($addressfulResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $addressfulResponse->sendContent();
    $addressfulHtml = (string) ob_get_clean();

    expect($addressfulHtml)->toContain('Bill To')
        ->and($addressfulHtml)->toContain('Billing Customer')
        ->and($addressfulHtml)->toContain('123 Billing Street')
        ->and($addressfulHtml)->toContain('Ship To')
        ->and($addressfulHtml)->toContain('Shipping Customer')
        ->and($addressfulHtml)->toContain('456 Shipping Avenue');

    OwnerContext::withOwner(null, fn (): int => $order->addresses()->detach());
    $addressless = $order->fresh(['items', 'addresses']) ?? $order;
    $addresslessResponse = (new GenerateInvoice)->download($addressless);
    expect($addresslessResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $addresslessResponse->sendContent();
    $addresslessHtml = (string) ob_get_clean();

    expect($addresslessHtml)->not->toContain('Bill To')
        ->and($addresslessHtml)->not->toContain('Ship To')
        ->and($addresslessHtml)->not->toContain('Billing Customer')
        ->and($addresslessHtml)->not->toContain('Shipping Customer');
});

it('falls back to the primary shipping address for document customer data', function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('docs.owner.enabled', false);

    $order = OwnerContext::withOwner(null, function (): Order {
        $order = Order::factory()->create([
            'order_number' => 'ORD-SHIPPING-FALLBACK-' . uniqid(),
            'currency' => 'MYR',
            'subtotal' => 3000,
            'grand_total' => 3000,
        ]);
        $shipping = Address::create([
            'line1' => '789 Shipping Road',
            'city' => 'Johor Bahru',
            'postcode' => '80000',
            'country_code' => 'MY',
            'metadata' => [
                Order::ADDRESS_CONTACT_METADATA_KEY => [
                    'first_name' => 'Shipping',
                    'last_name' => 'Fallback',
                    'email' => 'shipping-fallback@example.com',
                ],
            ],
        ]);

        $order->attachAddress($shipping, type: 'shipping', isPrimary: true);

        return $order->fresh(['addresses']) ?? $order;
    });

    $invoice = OwnerContext::withOwner(null, fn () => app(CreateOrderInvoiceDoc::class)->execute(
        $order,
        'txn-shipping-fallback',
        'chip',
    ));

    expect($invoice?->customer_data)->toMatchArray([
        'name' => 'Shipping Fallback',
        'email' => 'shipping-fallback@example.com',
    ]);
});
