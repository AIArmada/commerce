<?php

declare(strict_types=1);

use AIArmada\Jnt\Actions\Orders\CancelOrder;
use AIArmada\Jnt\Actions\Orders\CreateOrder;
use AIArmada\Jnt\Actions\Tracking\TrackParcel;
use AIArmada\Jnt\Actions\Waybills\PrintWaybill;
use AIArmada\Jnt\Data\AddressData;
use AIArmada\Jnt\Data\ItemData;
use AIArmada\Jnt\Data\OrderData;
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Services\JntExpressService;
use Spatie\LaravelData\DataCollection;

describe('CreateOrder action', function (): void {
    it('can be instantiated via constructor', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);

        $action = new CreateOrder($jntService);

        expect($action)->toBeInstanceOf(CreateOrder::class);
    });

    it('handles order creation', function (): void {
        $sender = new AddressData(
            name: 'John Doe',
            phone: '+60123456789',
            address: '123 Main Street',
            postCode: '50000',
            countryCode: 'MYS',
            state: 'Wilayah Persekutuan',
            city: 'Kuala Lumpur',
        );

        $receiver = new AddressData(
            name: 'Jane Doe',
            phone: '+60198765432',
            address: '456 Secondary Street',
            postCode: '10000',
            countryCode: 'MYS',
            state: 'Pulau Pinang',
            city: 'Penang',
        );

        $items = [
            new ItemData(
                name: 'Test Product',
                quantity: 1,
                weight: 500,
                price: 29.99,
            ),
        ];

        $packageInfo = new PackageInfoData(
            quantity: 1,
            weight: 500,
            value: 29.99,
            goodsType: 'ITN8',
            length: 10,
            width: 10,
            height: 10,
        );

        $expectedOrder = new OrderData(
            orderId: 'ORDER123',
            trackingNumber: 'JNT123456',
            sortingCode: 'SC001',
        );

        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('createOrder')
            ->once()
            ->withArgs(function ($s, $r, $i, $p, $orderId, $additional) {
                return $orderId === 'ORDER123';
            })
            ->andReturn($expectedOrder);

        $action = new CreateOrder($jntService);
        $result = $action->handle($sender, $receiver, $items, $packageInfo, 'ORDER123');

        expect($result)->toBeInstanceOf(OrderData::class);
        expect($result->orderId)->toBe('ORDER123');
        expect($result->trackingNumber)->toBe('JNT123456');
    });
});

describe('CancelOrder action', function (): void {
    it('can be instantiated via constructor', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);

        $action = new CancelOrder($jntService);

        expect($action)->toBeInstanceOf(CancelOrder::class);
    });

    it('handles order cancellation with string reason', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('cancelOrder')
            ->once()
            ->with('ORDER123', 'Customer requested', null)
            ->andReturn(['success' => true, 'message' => 'Order cancelled']);

        $action = new CancelOrder($jntService);
        $result = $action->handle('ORDER123', 'Customer requested');

        expect($result)->toBe(['success' => true, 'message' => 'Order cancelled']);
    });

    it('handles order cancellation with enum reason', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('cancelOrder')
            ->once()
            ->with('ORDER123', CancellationReason::CUSTOMER_REQUEST, 'JNT123456')
            ->andReturn(['success' => true]);

        $action = new CancelOrder($jntService);
        $result = $action->handle('ORDER123', CancellationReason::CUSTOMER_REQUEST, 'JNT123456');

        expect($result)->toBe(['success' => true]);
    });
});

describe('TrackParcel action', function (): void {
    it('can be instantiated via constructor', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);

        $action = new TrackParcel($jntService);

        expect($action)->toBeInstanceOf(TrackParcel::class);
    });

    it('handles parcel tracking by order ID', function (): void {
        /** @var DataCollection<int, TrackingDetailData> $details */
        $details = TrackingDetailData::collect([], DataCollection::class);

        $expectedTracking = new TrackingData(
            trackingNumber: 'JNT123456',
            orderId: 'ORDER123',
            details: $details,
        );

        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('trackParcel')
            ->once()
            ->with('ORDER123', null)
            ->andReturn($expectedTracking);

        $action = new TrackParcel($jntService);
        $result = $action->handle(orderId: 'ORDER123');

        expect($result)->toBeInstanceOf(TrackingData::class);
        expect($result->trackingNumber)->toBe('JNT123456');
    });

    it('handles parcel tracking by tracking number', function (): void {
        /** @var DataCollection<int, TrackingDetailData> $details */
        $details = TrackingDetailData::collect([], DataCollection::class);

        $expectedTracking = new TrackingData(
            trackingNumber: 'JNT123456',
            orderId: null,
            details: $details,
        );

        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('trackParcel')
            ->once()
            ->with(null, 'JNT123456')
            ->andReturn($expectedTracking);

        $action = new TrackParcel($jntService);
        $result = $action->handle(trackingNumber: 'JNT123456');

        expect($result)->toBeInstanceOf(TrackingData::class);
    });
});

describe('PrintWaybill action', function (): void {
    it('can be instantiated via constructor', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);

        $action = new PrintWaybill($jntService);

        expect($action)->toBeInstanceOf(PrintWaybill::class);
    });

    it('handles waybill printing with order ID only', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('printOrder')
            ->once()
            ->with('ORDER123', null, null)
            ->andReturn(['waybill_url' => 'https://example.com/waybill.pdf']);

        $action = new PrintWaybill($jntService);
        $result = $action->handle('ORDER123');

        expect($result)->toBe(['waybill_url' => 'https://example.com/waybill.pdf']);
    });

    it('handles waybill printing with tracking number', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('printOrder')
            ->once()
            ->with('ORDER123', 'JNT123456', null)
            ->andReturn(['waybill_url' => 'https://example.com/waybill.pdf']);

        $action = new PrintWaybill($jntService);
        $result = $action->handle('ORDER123', 'JNT123456');

        expect($result)->toBe(['waybill_url' => 'https://example.com/waybill.pdf']);
    });

    it('handles waybill printing with template name', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('printOrder')
            ->once()
            ->with('ORDER123', 'JNT123456', 'standard-a4')
            ->andReturn(['waybill_url' => 'https://example.com/waybill.pdf']);

        $action = new PrintWaybill($jntService);
        $result = $action->handle('ORDER123', 'JNT123456', 'standard-a4');

        expect($result)->toBe(['waybill_url' => 'https://example.com/waybill.pdf']);
    });
});
