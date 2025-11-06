<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\OrderData;
use AIArmada\Jnt\Events\OrderCreatedEvent;
use Illuminate\Support\Facades\Event;

test('OrderCreatedEvent → it exposes order data', function (): void {
    $orderData = new OrderData(
        orderId: 'ORDER123',
        trackingNumber: 'TRACK456',
        sortingCode: 'SC789',
    );

    $event = new OrderCreatedEvent($orderData);

    expect($event->order)->toBe($orderData)
        ->and($event->getOrderId())->toBe('ORDER123')
        ->and($event->getTrackingNumber())->toBe('TRACK456')
        ->and($event->hasTrackingNumber())->toBeTrue();
});

test('OrderCreatedEvent → it handles order without tracking number', function (): void {
    $orderData = new OrderData(
        orderId: 'ORDER123',
        trackingNumber: null,
    );

    $event = new OrderCreatedEvent($orderData);

    expect($event->getTrackingNumber())->toBeNull()
        ->and($event->hasTrackingNumber())->toBeFalse();
});

test('OrderCreatedEvent → it converts to array', function (): void {
    $orderData = new OrderData(
        orderId: 'ORDER123',
        trackingNumber: 'TRACK456',
        sortingCode: 'SC789',
    );

    $event = new OrderCreatedEvent($orderData);
    $array = $event->toArray();

    expect($array)->toBeArray()
        ->and($array['txlogisticId'])->toBe('ORDER123')
        ->and($array['billCode'])->toBe('TRACK456')
        ->and($array['sortingCode'])->toBe('SC789');
});

test('OrderCreatedEvent → it can be dispatched', function (): void {
    Event::fake();

    $orderData = new OrderData(orderId: 'ORDER123');

    OrderCreatedEvent::dispatch($orderData);

    Event::assertDispatched(OrderCreatedEvent::class);
});
