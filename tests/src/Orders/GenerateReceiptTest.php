<?php

declare(strict_types=1);

use AIArmada\Orders\Actions\GenerateReceipt;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Completed;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

describe('GenerateReceipt Action', function (): void {
    it('can be instantiated', function (): void {
        expect(new GenerateReceipt)->toBeInstanceOf(GenerateReceipt::class);
    });

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
