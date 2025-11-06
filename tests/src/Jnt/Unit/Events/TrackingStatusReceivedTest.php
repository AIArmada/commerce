<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Data\WebhookData;
use AIArmada\Jnt\Events\TrackingStatusReceived;

describe('TrackingStatusReceived Event', function (): void {
    beforeEach(function (): void {
        $this->details = [
            new TrackingDetailData(
                scanTime: '2024-01-15 09:00:00',
                description: 'Package collected',
                scanTypeCode: 'CC',
                scanTypeName: 'Collection',
                scanType: 'collection',
                scanNetworkName: 'KL Hub',
                scanNetworkProvince: 'Wilayah Persekutuan',
                scanNetworkCity: 'Kuala Lumpur'
            ),
            new TrackingDetailData(
                scanTime: '2024-01-16 14:00:00',
                description: 'Package delivered',
                scanTypeCode: 'DL',
                scanTypeName: 'Delivery',
                scanType: 'delivery',
                scanNetworkName: 'Penang Branch',
                scanNetworkProvince: 'Penang',
                scanNetworkCity: 'Georgetown'
            ),
        ];

        $this->webhookData = new WebhookData(
            billCode: 'JNTMY12345678',
            txlogisticId: 'ORDER-001',
            details: $this->details
        );

        $this->event = new TrackingStatusReceived($this->webhookData);
    });

    it('exposes webhook data', function (): void {
        expect($this->event->webhookData)->toBeInstanceOf(WebhookData::class)
            ->and($this->event->webhookData->billCode)->toBe('JNTMY12345678');
    });

    it('gets bill code', function (): void {
        expect($this->event->getBillCode())->toBe('JNTMY12345678');
    });

    it('gets txlogistic ID', function (): void {
        expect($this->event->getTxlogisticId())->toBe('ORDER-001');
    });

    it('gets latest status', function (): void {
        expect($this->event->getLatestStatus())->toBe('delivery');
    });

    it('gets latest description', function (): void {
        expect($this->event->getLatestDescription())->toBe('Package delivered');
    });

    it('gets latest location', function (): void {
        expect($this->event->getLatestLocation())->toBe('Penang Branch, Georgetown, Penang');
    });

    it('gets latest timestamp', function (): void {
        expect($this->event->getLatestTimestamp())->toBe('2024-01-16 14:00:00');
    });

    it('detects delivery status', function (): void {
        expect($this->event->isDelivered())->toBeTrue();
    });

    it('detects collection status', function (): void {
        $collectionData = new WebhookData(
            billCode: 'JNTMY12345678',
            txlogisticId: null,
            details: [
                new TrackingDetailData(
                    scanTime: '2024-01-15 09:00:00',
                    description: 'Collected',
                    scanTypeCode: 'CC',
                    scanTypeName: 'Collection',
                    scanType: 'collection'
                ),
            ]
        );

        $event = new TrackingStatusReceived($collectionData);

        expect($event->isCollected())->toBeTrue()
            ->and($event->isDelivered())->toBeFalse();
    });

    it('detects problem status', function (): void {
        $problemData = new WebhookData(
            billCode: 'JNTMY12345678',
            txlogisticId: null,
            details: [
                new TrackingDetailData(
                    scanTime: '2024-01-15 09:00:00',
                    description: 'Problem occurred',
                    scanTypeCode: 'PR',
                    scanTypeName: 'Problem',
                    scanType: 'problem'
                ),
            ]
        );

        $event = new TrackingStatusReceived($problemData);

        expect($event->hasProblem())->toBeTrue()
            ->and($event->isDelivered())->toBeFalse();
    });

    it('detects return status as problem', function (): void {
        $returnData = new WebhookData(
            billCode: 'JNTMY12345678',
            txlogisticId: null,
            details: [
                new TrackingDetailData(
                    scanTime: '2024-01-15 09:00:00',
                    description: 'Package returned',
                    scanTypeCode: 'RT',
                    scanTypeName: 'Return',
                    scanType: 'return'
                ),
            ]
        );

        $event = new TrackingStatusReceived($returnData);

        expect($event->hasProblem())->toBeTrue();
    });

    it('detects reject status as problem', function (): void {
        $rejectData = new WebhookData(
            billCode: 'JNTMY12345678',
            txlogisticId: null,
            details: [
                new TrackingDetailData(
                    scanTime: '2024-01-15 09:00:00',
                    description: 'Package rejected',
                    scanTypeCode: 'RJ',
                    scanTypeName: 'Reject',
                    scanType: 'reject'
                ),
            ]
        );

        $event = new TrackingStatusReceived($rejectData);

        expect($event->hasProblem())->toBeTrue();
    });

    it('detects signed status as delivered', function (): void {
        $signedData = new WebhookData(
            billCode: 'JNTMY12345678',
            txlogisticId: null,
            details: [
                new TrackingDetailData(
                    scanTime: '2024-01-16 14:00:00',
                    description: 'Package signed for',
                    scanTypeCode: 'SG',
                    scanTypeName: 'Signed',
                    scanType: 'signed'
                ),
            ]
        );

        $event = new TrackingStatusReceived($signedData);

        expect($event->isDelivered())->toBeTrue();
    });

    it('handles null txlogistic ID', function (): void {
        $data = new WebhookData(
            billCode: 'JNTMY12345678',
            txlogisticId: null,
            details: $this->details
        );

        $event = new TrackingStatusReceived($data);

        expect($event->getTxlogisticId())->toBeNull();
    });

    it('handles empty details array', function (): void {
        $data = new WebhookData(
            billCode: 'JNTMY12345678',
            txlogisticId: null,
            details: []
        );

        $event = new TrackingStatusReceived($data);

        expect($event->getLatestStatus())->toBeNull()
            ->and($event->getLatestDescription())->toBeNull()
            ->and($event->getLatestLocation())->toBeNull()
            ->and($event->getLatestTimestamp())->toBeNull()
            ->and($event->isDelivered())->toBeFalse()
            ->and($event->isCollected())->toBeFalse()
            ->and($event->hasProblem())->toBeFalse();
    });

    it('handles partial location data', function (): void {
        $partialData = new WebhookData(
            billCode: 'JNTMY12345678',
            txlogisticId: null,
            details: [
                new TrackingDetailData(
                    scanTime: '2024-01-15 09:00:00',
                    description: 'Collected',
                    scanTypeCode: 'CC',
                    scanTypeName: 'Collection',
                    scanType: 'collection',
                    scanNetworkCity: 'Kuala Lumpur'
                ),
            ]
        );

        $event = new TrackingStatusReceived($partialData);

        expect($event->getLatestLocation())->toBe('Kuala Lumpur');
    });

    it('returns null location when all location fields are empty', function (): void {
        $noLocationData = new WebhookData(
            billCode: 'JNTMY12345678',
            txlogisticId: null,
            details: [
                new TrackingDetailData(
                    scanTime: '2024-01-15 09:00:00',
                    description: 'Collected',
                    scanTypeCode: 'CC',
                    scanTypeName: 'Collection',
                    scanType: 'collection'
                ),
            ]
        );

        $event = new TrackingStatusReceived($noLocationData);

        expect($event->getLatestLocation())->toBeNull();
    });
});
