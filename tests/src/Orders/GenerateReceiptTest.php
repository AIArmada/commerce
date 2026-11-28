<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Features\Documents\Actions\CreateOrderInvoiceDoc;
use AIArmada\Orders\Features\Documents\Actions\CreateOrderReceiptDoc;
use AIArmada\Orders\Features\Documents\Actions\GenerateInvoice;
use AIArmada\Orders\Features\Documents\Actions\GenerateReceipt;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Completed;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

describe('GenerateReceipt Action', function (): void {
    it('uses the stable order number when downloading the html fallback', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-RECEIPT-123',
            'status' => Completed::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
            'paid_at' => now(),
        ]);

        $response = (new GenerateReceipt)->download($order);

        expect($response)->toBeInstanceOf(StreamedResponse::class)
            ->and((string) $response->headers->get('content-type'))->toContain('text/html')
            ->and((string) $response->headers->get('content-disposition'))->toContain('receipt-ORD-RECEIPT-123.html');
    });

    it('can save a receipt to a path', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-RECEIPT-456',
            'status' => Completed::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
            'paid_at' => now(),
        ]);
        $path = storage_path('app/test-receipt.pdf');
        $builder = Mockery::mock(PdfBuilder::class);
        $builder->shouldReceive('format')->andReturnSelf();
        $builder->shouldReceive('margins')->andReturnSelf();
        $builder->shouldReceive('name')->with('receipt-ORD-RECEIPT-456.pdf')->andReturnSelf();
        $builder->shouldReceive('save')->with($path)->andReturnSelf();

        Pdf::shouldReceive('view')->with('orders::pdf.invoice', Mockery::on(
            static fn (array $data): bool => ($data['documentTitle'] ?? null) === 'Receipt'
                && ($data['documentNumber'] ?? null) === 'ORD-RECEIPT-456',
        ))->andReturn($builder);

        expect((new GenerateReceipt)->save($order, $path))->toBe($path);
    });
});

it('rejects cross-owner document generation even when the caller holds an order model', function (): void {
    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', true);
    config()->set('orders.owner.auto_assign_on_create', true);

    $owner = User::factory()->create();
    $other = User::factory()->create();
    $order = OwnerContext::withOwner($owner, function () use ($owner): Order {
        $order = Order::query()->make([
            'order_number' => 'ORD-DOCUMENT-OWNER',
            'status' => Completed::class,
            'currency' => 'MYR',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'paid_at' => now(),
        ]);
        $order->assignOwner($owner);
        $order->save();

        return $order;
    });

    OwnerContext::withOwner($other, function () use ($order): void {
        expect(fn () => (new GenerateReceipt)->download($order))->toThrow(ModelNotFoundException::class)
            ->and(fn () => (new GenerateReceipt)->save($order, storage_path('app/foreign-receipt.pdf')))->toThrow(ModelNotFoundException::class)
            ->and(fn () => (new GenerateInvoice)->save($order, storage_path('app/foreign-invoice.pdf')))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(CreateOrderInvoiceDoc::class)->execute($order, 'txn-foreign', 'chip'))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(CreateOrderReceiptDoc::class)->execute($order, 'txn-foreign', 'chip'))->toThrow(ModelNotFoundException::class);
    });
});
