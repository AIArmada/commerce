<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\OrderData;
use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Services\JntExpressService;
use Illuminate\Support\Facades\Http;

function createTestService(): JntExpressService
{
    return new JntExpressService(
        customerCode: 'TEST123',
        password: 'password',
        config: [
            'environment' => 'testing',
            'base_urls' => ['testing' => 'https://demo.api.test'],
            'api_account' => '640826271705595946',
            'private_key' => '8e88c8477d4e4939859c560192fcafbc',
            'http' => [
                'retry_times' => 0,
                'timeout' => 30,
                'connect_timeout' => 10,
            ],
        ]
    );
}

describe('Batch Create Orders', function (): void {
    it('creates multiple orders successfully', function (): void {
        $service = new JntExpressService(
            customerCode: 'TEST123',
            password: 'password',
            config: [
                'environment' => 'testing',
                'base_urls' => ['testing' => 'https://demo.api.test'],
                'api_account' => '640826271705595946',
                'private_key' => '8e88c8477d4e4939859c560192fcafbc',
                'http' => [
                    'retry_times' => 0,
                    'timeout' => 30,
                    'connect_timeout' => 10,
                ],
            ]
        );

        Http::fake([
            '*/api/order/addOrder' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER1', 'billCode' => 'TN001']])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER2', 'billCode' => 'TN002']])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER3', 'billCode' => 'TN003']]),
        ]);

        $orders = [
            ['orderId' => 'ORDER1', 'sender' => [], 'receiver' => [], 'items' => [], 'packageInfo' => []],
            ['orderId' => 'ORDER2', 'sender' => [], 'receiver' => [], 'items' => [], 'packageInfo' => []],
            ['orderId' => 'ORDER3', 'sender' => [], 'receiver' => [], 'items' => [], 'packageInfo' => []],
        ];

        $result = $service->batchCreateOrders($orders);

        expect($result['successful'])->toHaveCount(3);
        expect($result['failed'])->toHaveCount(0);
        expect($result['successful'][0])->toBeInstanceOf(OrderData::class);
        expect($result['successful'][0]->orderId)->toBe('ORDER1');
        expect($result['successful'][1]->orderId)->toBe('ORDER2');
        expect($result['successful'][2]->orderId)->toBe('ORDER3');
    });

    it('handles partial failures gracefully', function (): void {
        Http::fake([
            '*/api/order/addOrder' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER1', 'billCode' => 'TN001']])
                ->push(['code' => '0', 'msg' => 'Duplicate order ID'], 500)
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER3', 'billCode' => 'TN003']]),
        ]);

        $orders = [
            ['orderId' => 'ORDER1', 'sender' => [], 'receiver' => [], 'items' => [], 'packageInfo' => []],
            ['orderId' => 'ORDER2', 'sender' => [], 'receiver' => [], 'items' => [], 'packageInfo' => []],
            ['orderId' => 'ORDER3', 'sender' => [], 'receiver' => [], 'items' => [], 'packageInfo' => []],
        ];

        $result = createTestService()->batchCreateOrders($orders);

        expect($result['successful'])->toHaveCount(2);
        expect($result['failed'])->toHaveCount(1);
        expect($result['successful'][0]->orderId)->toBe('ORDER1');
        expect($result['successful'][1]->orderId)->toBe('ORDER3');
        expect($result['failed'][0]['orderId'])->toBe('ORDER2'); // The 2nd order (ORDER2) is the one that failed
        expect($result['failed'][0]['error'])->toContain('500');
    });

    it('returns empty arrays when no orders provided', function (): void {
        $result = createTestService()->batchCreateOrders([]);

        expect($result['successful'])->toHaveCount(0);
        expect($result['failed'])->toHaveCount(0);
    });

    it('includes exception details in failed results', function (): void {
        Http::fake([
            '*/api/order/addOrder' => Http::response(['code' => '0', 'msg' => 'Invalid data'], 400),
        ]);

        $orders = [
            ['orderId' => 'ORDER1', 'sender' => [], 'receiver' => [], 'items' => [], 'packageInfo' => []],
        ];

        $result = createTestService()->batchCreateOrders($orders);

        expect($result['failed'])->toHaveCount(1);
        expect($result['failed'][0])->toHaveKey('orderId');
        expect($result['failed'][0])->toHaveKey('error');
        expect($result['failed'][0])->toHaveKey('exception');
        expect($result['failed'][0]['exception'])->toBeInstanceOf(Throwable::class);
    });
});

describe('Batch Track Parcels', function (): void {
    it('tracks multiple parcels by order IDs', function (): void {
        Http::fake([
            '*/api/logistics/trace' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER1', 'billCode' => 'TN001', 'details' => []]])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER2', 'billCode' => 'TN002', 'details' => []]])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER3', 'billCode' => 'TN003', 'details' => []]]),
        ]);

        $result = createTestService()->batchTrackParcels(orderIds: ['ORDER1', 'ORDER2', 'ORDER3']);

        expect($result['successful'])->toHaveCount(3);
        expect($result['failed'])->toHaveCount(0);
        expect($result['successful'][0])->toBeInstanceOf(TrackingData::class);
        expect($result['successful'][0]->orderId)->toBe('ORDER1');
    });

    it('tracks multiple parcels by tracking numbers', function (): void {
        Http::fake([
            '*/api/logistics/trace' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER1', 'billCode' => 'TN001', 'details' => []]])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER2', 'billCode' => 'TN002', 'details' => []]]),
        ]);

        $result = createTestService()->batchTrackParcels(trackingNumbers: ['TN001', 'TN002']);

        expect($result['successful'])->toHaveCount(2);
        expect($result['failed'])->toHaveCount(0);
        expect($result['successful'][0]->trackingNumber)->toBe('TN001');
        expect($result['successful'][1]->trackingNumber)->toBe('TN002');
    });

    it('handles mixed order IDs and tracking numbers', function (): void {
        Http::fake([
            '*/api/logistics/trace' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER1', 'billCode' => 'TN001', 'details' => []]])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER2', 'billCode' => 'TN002', 'details' => []]]),
        ]);

        $result = createTestService()->batchTrackParcels(
            orderIds: ['ORDER1'],
            trackingNumbers: ['TN002']
        );

        expect($result['successful'])->toHaveCount(2);
        expect($result['failed'])->toHaveCount(0);
    });

    it('handles tracking failures gracefully', function (): void {
        Http::fake([
            '*/api/logistics/trace' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER1', 'billCode' => 'TN001', 'details' => []]])
                ->push(['code' => '0', 'msg' => 'Order not found'], 404)
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER3', 'billCode' => 'TN003', 'details' => []]]),
        ]);

        $result = createTestService()->batchTrackParcels(orderIds: ['ORDER1', 'ORDER2', 'ORDER3']);

        expect($result['successful'])->toHaveCount(2);
        expect($result['failed'])->toHaveCount(1);
        expect($result['failed'][0]['identifier'])->toBe('ORDER2');
        expect($result['failed'][0]['type'])->toBe('orderId');
        expect($result['failed'][0])->toHaveKey('exception');
    });

    it('returns empty arrays when no identifiers provided', function (): void {
        $result = createTestService()->batchTrackParcels();

        expect($result['successful'])->toHaveCount(0);
        expect($result['failed'])->toHaveCount(0);
    });
});

describe('Batch Cancel Orders', function (): void {
    it('cancels multiple orders successfully', function (): void {
        Http::fake([
            '*/api/order/cancelOrder' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['status' => 'cancelled']])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['status' => 'cancelled']])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['status' => 'cancelled']]),
        ]);

        $result = createTestService()->batchCancelOrders(
            orderIds: ['ORDER1', 'ORDER2', 'ORDER3'],
            reason: CancellationReason::OUT_OF_STOCK
        );

        expect($result['successful'])->toHaveCount(3);
        expect($result['failed'])->toHaveCount(0);
        expect($result['successful'][0]['orderId'])->toBe('ORDER1');
        expect($result['successful'][0]['data']['status'])->toBe('cancelled');
    });

    it('accepts custom string reason', function (): void {
        Http::fake([
            '*/api/order/cancelOrder' => Http::response(['code' => '1', 'msg' => 'success', 'data' => ['status' => 'cancelled']]),
        ]);

        $result = createTestService()->batchCancelOrders(
            orderIds: ['ORDER1'],
            reason: 'Custom cancellation reason'
        );

        expect($result['successful'])->toHaveCount(1);
        expect($result['failed'])->toHaveCount(0);
    });

    it('handles cancellation failures gracefully', function (): void {
        Http::fake([
            '*/api/order/cancelOrder' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['status' => 'cancelled']])
                ->push(['code' => '0', 'msg' => 'Order already cancelled'], 400)
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['status' => 'cancelled']]),
        ]);

        $result = createTestService()->batchCancelOrders(
            orderIds: ['ORDER1', 'ORDER2', 'ORDER3'],
            reason: CancellationReason::CUSTOMER_CHANGED_MIND
        );

        expect($result['successful'])->toHaveCount(2);
        expect($result['failed'])->toHaveCount(1);
        expect($result['failed'][0]['orderId'])->toBe('ORDER2');
        expect($result['failed'][0])->toHaveKey('error');
        expect($result['failed'][0])->toHaveKey('exception');
    });

    it('returns empty arrays when no order IDs provided', function (): void {
        $result = createTestService()->batchCancelOrders(
            orderIds: [],
            reason: CancellationReason::OUT_OF_STOCK
        );

        expect($result['successful'])->toHaveCount(0);
        expect($result['failed'])->toHaveCount(0);
    });
});

describe('Batch Print Waybills', function (): void {
    it('prints multiple waybills successfully', function (): void {
        Http::fake([
            '*/api/order/printOrder' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['content' => 'BASE64_PDF_1']])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['content' => 'BASE64_PDF_2']])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['content' => 'BASE64_PDF_3']]),
        ]);

        $result = createTestService()->batchPrintWaybills(orderIds: ['ORDER1', 'ORDER2', 'ORDER3']);

        expect($result['successful'])->toHaveCount(3);
        expect($result['failed'])->toHaveCount(0);
        expect($result['successful'][0]['orderId'])->toBe('ORDER1');
        expect($result['successful'][0]['data']['content'])->toBe('BASE64_PDF_1');
    });

    it('uses custom template for all waybills', function (): void {
        Http::fake([
            '*/api/order/printOrder' => Http::response(['code' => '1', 'msg' => 'success', 'data' => ['content' => 'BASE64_PDF']]),
        ]);

        $result = createTestService()->batchPrintWaybills(
            orderIds: ['ORDER1', 'ORDER2'],
            templateName: 'CUSTOM_TEMPLATE'
        );

        expect($result['successful'])->toHaveCount(2);
        expect($result['failed'])->toHaveCount(0);

        // Verify that printOrder was called twice (once for each order)
        Http::assertSentCount(2);
    });

    it('handles print failures gracefully', function (): void {
        Http::fake([
            '*/api/order/printOrder' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['content' => 'BASE64_PDF_1']])
                ->push(['code' => '0', 'msg' => 'Order not ready for printing'], 400)
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['content' => 'BASE64_PDF_3']]),
        ]);

        $result = createTestService()->batchPrintWaybills(orderIds: ['ORDER1', 'ORDER2', 'ORDER3']);

        expect($result['successful'])->toHaveCount(2);
        expect($result['failed'])->toHaveCount(1);
        expect($result['failed'][0]['orderId'])->toBe('ORDER2');
        expect($result['failed'][0])->toHaveKey('error');
        expect($result['failed'][0])->toHaveKey('exception');
    });

    it('returns empty arrays when no order IDs provided', function (): void {
        $result = createTestService()->batchPrintWaybills(orderIds: []);

        expect($result['successful'])->toHaveCount(0);
        expect($result['failed'])->toHaveCount(0);
    });
});

describe('Batch Operations - Integration Scenarios', function (): void {
    it('handles complete batch workflow', function (): void {
        Http::fake([
            '*/api/order/addOrder' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER1', 'billCode' => 'TN001']])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER2', 'billCode' => 'TN002']]),
            '*/api/logistics/trace' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER1', 'billCode' => 'TN001', 'details' => []]])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'ORDER2', 'billCode' => 'TN002', 'details' => []]]),
            '*/api/order/printOrder' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['content' => 'PDF1']])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['content' => 'PDF2']]),
        ]);

        // Step 1: Create orders
        $createResult = createTestService()->batchCreateOrders([
            ['txlogisticId' => 'ORDER1', 'sender' => [], 'receiver' => [], 'items' => [], 'packageInfo' => []],
            ['txlogisticId' => 'ORDER2', 'sender' => [], 'receiver' => [], 'items' => [], 'packageInfo' => []],
        ]);

        expect($createResult['successful'])->toHaveCount(2);

        // Step 2: Track orders
        $orderIds = array_map(fn (OrderData $order): string => $order->orderId, $createResult['successful']);
        $trackResult = createTestService()->batchTrackParcels(orderIds: $orderIds);

        expect($trackResult['successful'])->toHaveCount(2);

        // Step 3: Print waybills
        $printResult = createTestService()->batchPrintWaybills(orderIds: $orderIds);

        expect($printResult['successful'])->toHaveCount(2);
    });

    it('provides detailed error information for debugging', function (): void {
        Http::fake([
            '*/api/order/addOrder' => Http::response(['code' => '0', 'msg' => 'Validation failed'], 400),
        ]);

        $result = createTestService()->batchCreateOrders([
            ['txlogisticId' => 'ORDER1', 'sender' => [], 'receiver' => [], 'items' => [], 'packageInfo' => []],
        ]);

        expect($result['failed'])->toHaveCount(1);
        expect($result['failed'][0])->toHaveKeys(['orderId', 'error', 'exception']);

        $exception = $result['failed'][0]['exception'];
        expect($exception)->toBeInstanceOf(Throwable::class);
        expect($exception->getMessage())->toContain('400');
    });
});
