<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\PrintWaybillData;
use AIArmada\Jnt\Events\WaybillPrintedEvent;
use Illuminate\Support\Facades\Event;

test('WaybillPrintedEvent → it exposes waybill data', function (): void {
    $waybillData = new PrintWaybillData(
        orderId: 'ORDER123',
        trackingNumber: 'TRACK456',
        base64Content: base64_encode('PDF content here'),
        urlContent: null,
        isMultiParcel: false,
        templateName: 'default',
    );

    $event = new WaybillPrintedEvent($waybillData);

    expect($event->waybill)->toBe($waybillData)
        ->and($event->getOrderId())->toBe('ORDER123')
        ->and($event->getTrackingNumber())->toBe('TRACK456')
        ->and($event->hasTrackingNumber())->toBeTrue()
        ->and($event->getTemplateName())->toBe('default');
});

test('WaybillPrintedEvent → it detects base64 content', function (): void {
    $waybillData = new PrintWaybillData(
        orderId: 'ORDER123',
        trackingNumber: null,
        base64Content: base64_encode('PDF content'),
        urlContent: null,
        isMultiParcel: false,
    );

    $event = new WaybillPrintedEvent($waybillData);

    expect($event->hasBase64Content())->toBeTrue()
        ->and($event->hasUrlContent())->toBeFalse()
        ->and($event->getPdfContent())->not->toBeNull();
});

test('WaybillPrintedEvent → it detects URL content', function (): void {
    $waybillData = new PrintWaybillData(
        orderId: 'ORDER123',
        trackingNumber: null,
        base64Content: null,
        urlContent: 'https://example.com/waybill.pdf',
        isMultiParcel: true,
    );

    $event = new WaybillPrintedEvent($waybillData);

    expect($event->hasUrlContent())->toBeTrue()
        ->and($event->hasBase64Content())->toBeFalse()
        ->and($event->getDownloadUrl())->toBe('https://example.com/waybill.pdf');
});

test('WaybillPrintedEvent → it handles waybill without tracking number', function (): void {
    $waybillData = new PrintWaybillData(
        orderId: 'ORDER123',
        trackingNumber: null,
        base64Content: null,
        urlContent: null,
        isMultiParcel: false,
    );

    $event = new WaybillPrintedEvent($waybillData);

    expect($event->getTrackingNumber())->toBeNull()
        ->and($event->hasTrackingNumber())->toBeFalse();
});

test('WaybillPrintedEvent → it provides file size', function (): void {
    $waybillData = new PrintWaybillData(
        orderId: 'ORDER123',
        trackingNumber: null,
        base64Content: base64_encode('PDF content here'),
        urlContent: null,
        isMultiParcel: false,
    );

    $event = new WaybillPrintedEvent($waybillData);

    expect($event->getFileSize())->not->toBeNull()
        ->and($event->getFileSize())->toContain('B'); // Contains byte unit
});

test('WaybillPrintedEvent → it can save PDF', function (): void {
    $waybillData = new PrintWaybillData(
        orderId: 'ORDER123',
        trackingNumber: null,
        base64Content: base64_encode('%PDF-1.4 fake content'),
        urlContent: null,
        isMultiParcel: false,
    );

    $event = new WaybillPrintedEvent($waybillData);

    $path = sys_get_temp_dir().'/test_waybill.pdf';

    expect($event->savePdf($path))->toBeTrue();

    // Cleanup
    if (file_exists($path)) {
        unlink($path);
    }
});

test('WaybillPrintedEvent → it can be dispatched', function (): void {
    Event::fake();

    $waybillData = new PrintWaybillData(
        orderId: 'ORDER123',
        trackingNumber: null,
        base64Content: null,
        urlContent: null,
        isMultiParcel: false,
    );

    WaybillPrintedEvent::dispatch($waybillData);

    Event::assertDispatched(WaybillPrintedEvent::class);
});
