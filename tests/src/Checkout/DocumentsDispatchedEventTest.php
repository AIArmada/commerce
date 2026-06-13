<?php

declare(strict_types=1);

use AIArmada\Checkout\Events\DocumentsDispatched;
use AIArmada\Checkout\Jobs\GenerateCheckoutDocumentsJob;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\DispatchDocumentGenerationStep;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Orders\Models\Order;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

describe('DocumentsDispatched event', function (): void {
    it('skips document dispatch when checkout document generation remains at its defaults', function (): void {
        Bus::fake();

        $session = CheckoutSession::create([
            'cart_id' => 'cart-docs-defaults',
            'order_id' => 'order-docs-defaults',
            'selected_payment_gateway' => 'chip',
        ]);

        $step = app(DispatchDocumentGenerationStep::class);
        $result = $step->handle($session);

        Bus::assertNothingDispatched();

        expect($result->isSuccessful())->toBeTrue()
            ->and($result->status->value)->toBe('skipped')
            ->and($result->data)->toMatchArray([])
            ->and($result->message)->toBe('No documents configured for generation');
    });

    it('fires after dispatching document generation', function (): void {
        Event::fake([DocumentsDispatched::class]);
        Bus::fake();

        config()->set('checkout.documents.generate_invoice', true);
        config()->set('checkout.documents.generate_receipt', false);
        config()->set('checkout.documents.queue', 'documents');

        $session = CheckoutSession::create([
            'cart_id' => 'cart-docs-1',
            'order_id' => 'order-123',
            'selected_payment_gateway' => 'chip',
        ]);

        $step = app(DispatchDocumentGenerationStep::class);
        $step->handle($session);

        Bus::assertDispatched(GenerateCheckoutDocumentsJob::class, function (GenerateCheckoutDocumentsJob $job) use ($session): bool {
            return $job->orderId === $session->order_id
                && $job->sessionId === $session->id
                && $job->documentTypes === ['invoice']
                && $job->ownerType === null
                && $job->ownerId === null
                && $job->ownerIsGlobal === true;
        });

        Event::assertDispatched(DocumentsDispatched::class, function (DocumentsDispatched $event) use ($session): bool {
            return $event->orderId === $session->order_id
                && $event->session->is($session)
                && $event->documents === ['invoice']
                && $event->queue === 'documents';
        });
    });

    it('generates configured documents when the dispatched job runs', function (): void {
        config()->set('docs.owner.enabled', false);
        config()->set('orders.owner.enabled', false);
        config()->set('orders.integrations.docs.generate_pdf', false);

        $order = checkoutDocumentGenerationOrder('job-runs');

        $session = CheckoutSession::create([
            'cart_id' => 'cart-docs-job',
            'order_id' => $order->id,
            'payment_id' => 'pay_checkout_docs',
            'selected_payment_gateway' => 'chip',
            'payment_data' => [
                'transaction_id' => 'txn_checkout_docs',
                'gateway' => 'chip',
            ],
        ]);

        (new GenerateCheckoutDocumentsJob(
            sessionId: $session->id,
            orderId: $order->id,
            documentTypes: ['invoice', 'receipt'],
            ownerIsGlobal: true,
        ))->handle();

        $invoice = Doc::query()
            ->where('docable_type', $order->getMorphClass())
            ->where('docable_id', $order->getKey())
            ->where('doc_type', DocType::Invoice->value)
            ->first();

        $receipt = Doc::query()
            ->where('docable_type', $order->getMorphClass())
            ->where('docable_id', $order->getKey())
            ->where('doc_type', DocType::Receipt->value)
            ->first();

        expect($invoice)->not->toBeNull()
            ->and($receipt)->not->toBeNull()
            ->and(data_get($invoice?->metadata, 'payment_gateway'))->toBe('chip')
            ->and(data_get($receipt?->metadata, 'payment_transaction_id'))->toBe('txn_checkout_docs');
    });
});

function checkoutDocumentGenerationOrder(string $suffix): Order
{
    $order = Order::query()->create([
        'order_number' => 'ORD-CHECKOUT-DOCS-' . $suffix,
        'subtotal' => 12000,
        'discount_total' => 1000,
        'shipping_total' => 500,
        'tax_total' => 600,
        'grand_total' => 12100,
        'currency' => 'MYR',
        'paid_at' => now(),
    ]);

    $order->items()->create([
        'name' => 'Checkout Document Product',
        'sku' => 'checkout-doc-product-' . $suffix,
        'quantity' => 1,
        'unit_price' => 12000,
        'discount_amount' => 1000,
        'tax_amount' => 600,
        'currency' => 'MYR',
    ]);

    $order->addresses()->create([
        'type' => 'billing',
        'first_name' => 'Checkout',
        'last_name' => 'Customer',
        'line1' => '123 Checkout Street',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country_code' => 'MY',
        'country' => 'MY',
        'email' => 'checkout-docs+' . $suffix . '@example.com',
        'phone' => '0123456789',
    ]);

    return $order->fresh(['items', 'billingAddress']) ?? $order;
}
