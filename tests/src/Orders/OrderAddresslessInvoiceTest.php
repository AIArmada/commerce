<?php

declare(strict_types=1);

use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\Fixtures\TestOwner;
use AIArmada\Docs\Data\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Features\Documents\Actions\CreateOrderInvoiceDoc;
use AIArmada\Orders\Features\Documents\Actions\GenerateInvoice;
use AIArmada\Orders\Features\Lifecycle\Actions\CreateOrder;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

    $order->payments()->create([
        'gateway' => 'chip',
        'transaction_id' => 'paid-digital-' . $order->getKey(),
        'amount' => $order->grand_total,
        'currency' => $order->currency,
        'status' => PaymentStatus::Completed,
        'paid_at' => now(),
    ]);

    $snapshot = $order->fresh(['items', 'addresses']) ?? $order;

    expect($snapshot)->toBeInstanceOf(Order::class)
        ->and($snapshot?->relationLoaded('addresses'))->toBeTrue()
        ->and($snapshot?->addresses)->toBeEmpty()
        ->and($snapshot?->primaryAddress('billing'))->toBeNull()
        ->and($snapshot?->primaryAddress('shipping'))->toBeNull()
        ->and(Address::query()->count())->toBe(0);

    $invoiceId = OwnerContext::withOwner(null, fn () => app(CreateOrderInvoiceDoc::class)->execute(
        $snapshot,
        'txn-digital-order',
        'chip',
    ));
    $invoice = Doc::query()->findOrFail($invoiceId);

    expect($invoice)->not->toBeNull()
        ->and($invoice?->doc_type)->toBe(DocType::Invoice->value);

    view()->addNamespace('orders', dirname(__DIR__, 3) . '/packages/orders/resources/views');

    $response = (new GenerateInvoice)->download((string) $snapshot->getKey());
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
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
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

    $order->payments()->create([
        'gateway' => 'chip',
        'transaction_id' => 'paid-addressful-' . $order->getKey(),
        'amount' => $order->grand_total,
        'currency' => $order->currency,
        'status' => PaymentStatus::Completed,
        'paid_at' => now(),
    ]);

    $invoiceId = OwnerContext::withOwner(null, fn () => app(CreateOrderInvoiceDoc::class)->execute(
        $order,
        'txn-addressful-order',
        'chip',
    ));
    $invoice = Doc::query()->findOrFail($invoiceId);

    expect($invoice)->not->toBeNull()
        ->and($invoice?->customer_data)->toMatchArray([
            'name' => 'Billing Customer',
            'email' => 'billing@example.com',
            'company' => 'Billing Company',
        ]);

    view()->addNamespace('orders', dirname(__DIR__, 3) . '/packages/orders/resources/views');

    $addressfulResponse = (new GenerateInvoice)->download((string) $order->getKey());
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
    $addresslessResponse = (new GenerateInvoice)->download((string) $addressless->getKey());
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
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
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

    $order->payments()->create([
        'gateway' => 'chip',
        'transaction_id' => 'paid-shipping-' . $order->getKey(),
        'amount' => $order->grand_total,
        'currency' => $order->currency,
        'status' => PaymentStatus::Completed,
        'paid_at' => now(),
    ]);

    $invoiceId = OwnerContext::withOwner(null, fn () => app(CreateOrderInvoiceDoc::class)->execute(
        $order,
        'txn-shipping-fallback',
        'chip',
    ));
    $invoice = Doc::query()->findOrFail($invoiceId);

    expect($invoice?->customer_data)->toMatchArray([
        'name' => 'Shipping Fallback',
        'email' => 'shipping-fallback@example.com',
    ]);
});

it('requires the owning context to render an owned order invoice', function (): void {
    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.auto_assign_on_create', true);
    config()->set('docs.owner.enabled', false);

    Schema::dropIfExists('test_owners');
    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $owner = TestOwner::query()->create(['name' => 'Invoice Owner']);

    $order = OwnerContext::withOwner($owner, function (): Order {
        $order = Order::factory()->create([
            'order_number' => 'ORD-OWNED-' . uniqid(),
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
        ]);
        $order->items()->create([
            'name' => 'Physical Product',
            'quantity' => 1,
            'unit_price' => 10000,
            'currency' => 'MYR',
        ]);

        $billing = Address::create([
            'line1' => '7 Owned Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
            'metadata' => [
                Order::ADDRESS_CONTACT_METADATA_KEY => [
                    'first_name' => 'Owned',
                    'last_name' => 'Billing',
                ],
            ],
        ]);

        $order->attachAddress($billing, type: 'billing', isPrimary: true);

        return $order->fresh(['items', 'addresses']) ?? $order;
    });

    // The ambient context resolves the default test owner, not $owner.
    view()->addNamespace('orders', dirname(__DIR__, 3) . '/packages/orders/resources/views');

    expect(fn () => (new GenerateInvoice)->download((string) $order->getKey()))
        ->toThrow(ModelNotFoundException::class);

    $response = OwnerContext::withOwner($owner, fn () => (new GenerateInvoice)->download((string) $order->getKey()));
    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $html = (string) ob_get_clean();

    expect($html)->toContain('Bill To')
        ->and($html)->toContain('Owned Billing')
        ->and($html)->toContain('7 Owned Street');

    OwnerContext::withOwner($owner, function () use ($order): void {
        expect($order->refresh()->invoice_number)->not->toBeNull();
    });
});
